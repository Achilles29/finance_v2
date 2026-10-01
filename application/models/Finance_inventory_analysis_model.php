<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH.'libraries/Report_workspace.php';
require_once APPPATH.'libraries/Finance_division_revenue.php';
require_once APPPATH.'models/Procurement_report_model.php';

/** Read-only inventory efficiency. Snapshot, movements, receipts and revenue
 * remain separate measurements; a reconciliation residual is never usage. */
class Finance_inventory_analysis_model extends CI_Model
{
    private function rows(string $sql,array $binds=[]): array
    {
        $q=$this->db->query($sql,$binds);
        if(!$q){throw new RuntimeException('Sumber analisis persediaan belum dapat dibaca.');}
        $rows=$q->result_array();
        if(count($rows)>50000){throw new InvalidArgumentException('Rincian persediaan terlalu banyak. Persempit rentang bulan.');}
        return $rows;
    }

    public function divisions(): array
    {
        return $this->rows('SELECT id,code,name FROM mst_operational_division ORDER BY name');
    }

    private static function location(string $destination): string
    {
        return str_ends_with($destination,'_EVENT')?'EVENT':'REGULAR';
    }

    private static function accepts(array $f,int $division,string $location): bool
    {
        return (!$f['division_id'] || $f['division_id']===$division) && ($f['location']==='ALL' || $f['location']===$location);
    }

    private function stocks(string $kind,string $from,string $to): array
    {
        $material=$kind==='MATERIAL';
        $table=$material?'inv_division_monthly_stock':'inv_component_monthly_stock';
        $location=$material?'destination_type':'location_type';
        $where=$material?' AND material_id IS NOT NULL':'';
        $waste=$material?'GREATEST(waste_total_value,discarded_total_value)':'waste_total_value';
        $spoil=$material?'spoilage_total_value':'spoil_total_value';
        $process=$material?'process_loss_total_value':'0';
        $variance=$material?'variance_total_value':'0';
        $qty=$material?'closing_qty_content':'closing_qty';
        return $this->rows("SELECT DATE_FORMAT(month_key,'%Y-%m') month,COALESCE(division_id,0) division_id,$location destination,
            COUNT(*) profiles,SUM(opening_total_value) opening,SUM(in_total_value) incoming,SUM(out_total_value) outgoing,
            SUM(total_value) closing,SUM($waste) waste,SUM($spoil) spoil,SUM($process) process_loss,SUM($variance) variance,
            SUM(adjustment_plus_total_value) plus,SUM(adjustment_minus_total_value) minus,
            SUM(CASE WHEN opening_total_value<0 OR total_value<0 OR $qty<0 THEN 1 ELSE 0 END) negative_profiles,
            MAX(last_movement_date) last_date
            FROM $table WHERE month_key BETWEEN ? AND ? $where GROUP BY month,division_id,$location",[$from,$to]);
    }

    private function opnames(string $kind,string $from,string $to): array
    {
        $material=$kind==='MATERIAL';$table=$material?'inv_division_monthly_opname':'inv_component_monthly_opname';
        $location=$material?'destination_type':'location_type';$where=$material?' AND material_id IS NOT NULL':'';
        $result=[];
        foreach($this->rows("SELECT DATE_FORMAT(month_key,'%Y-%m') month,COALESCE(division_id,0) division_id,$location destination,
            SUM(total_value) value,COUNT(*) profiles,SUM(CASE WHEN total_value<0 THEN 1 ELSE 0 END) negative_profiles,
            MAX(generated_at) generated_at FROM $table WHERE month_key BETWEEN ? AND ? $where
            GROUP BY month,division_id,$location",[$from,$to]) as $row){
            $key=$row['month'].'|'.$row['division_id'].'|'.self::location($row['destination']);
            if(!isset($result[$key])){$result[$key]=['value'=>0,'profiles'=>0,'negative_profiles'=>0,'generated_at'=>''];}
            $result[$key]['value']+=Report_workspace::cents($row['value']);
            $result[$key]['profiles']+=(int)$row['profiles'];$result[$key]['negative_profiles']+=(int)$row['negative_profiles'];
            $result[$key]['generated_at']=max($result[$key]['generated_at'],$row['generated_at']);
        }
        return $result;
    }

    private function materialUsage(string $from,string $to): array
    {
        return $this->rows("SELECT DATE_FORMAT(m.movement_date,'%Y-%m') month,COALESCE(m.division_id,0) division_id,m.destination_type destination,
            SUM(CASE WHEN COALESCE(o.movement_type,m.movement_type)='USAGE_OUT' THEN ROUND(-m.qty_content_delta*m.unit_cost,2) ELSE 0 END) used,
            SUM(CASE WHEN COALESCE(o.movement_type,m.movement_type)='TRANSFER_OUT' THEN ROUND(-m.qty_content_delta*m.unit_cost,2) ELSE 0 END) transferred,
            SUM(CASE WHEN m.qty_content_delta!=0 AND m.unit_cost<=0 THEN 1 ELSE 0 END) unvalued
            FROM inv_stock_movement_log m LEFT JOIN inv_stock_movement_log o ON o.id=m.reversal_of_movement_id
            WHERE m.movement_scope='DIVISION' AND m.material_id IS NOT NULL AND m.movement_date BETWEEN ? AND ?
              AND COALESCE(o.movement_type,m.movement_type) IN ('USAGE_OUT','TRANSFER_OUT')
            GROUP BY month,m.division_id,m.destination_type",[$from,$to]);
    }

    public function lossRows(string $from,string $to): array
    {
        $type='COALESCE(o.movement_type,m.movement_type)';$category='COALESCE(o.adjustment_category,m.adjustment_category)';
        $loss="CASE WHEN $type IN ('DISCARDED_OUT','WASTE_OUT') OR $category='WASTE' THEN 'WASTE'
            WHEN $type='SPOIL_OUT' OR $category='SPOILAGE' THEN 'SPOIL'
            WHEN $type='PROCESS_LOSS_OUT' OR $category='PROCESS_LOSS' THEN 'PROCESS_LOSS'
            WHEN $type='VARIANCE_OUT' OR $category='VARIANCE' THEN 'VARIANCE'
            WHEN $type='ADJUSTMENT' AND COALESCE(o.qty_content_delta,m.qty_content_delta)<0 THEN 'ADJUSTMENT_MINUS' ELSE NULL END";
        $material=$this->rows("SELECT * FROM (SELECT 'MATERIAL' kind,m.movement_no,m.movement_date event_date,
            COALESCE(m.division_id,0) division_id,m.destination_type destination,m.material_id asset_id,
            COALESCE(NULLIF(m.profile_name,''),a.material_name,'Tanpa nama') asset_name,
            COALESCE(m.profile_key,'') profile,COALESCE(m.profile_brand,'') brand,
            COALESCE(m.profile_content_uom_code,u.code,'-') unit,$loss loss_kind,
            COALESCE(o.adjustment_reason_code,m.adjustment_reason_code,'') reason,
            -m.qty_content_delta qty,ROUND(-m.qty_content_delta*m.unit_cost,2) value,m.unit_cost,
            m.ref_table source_table,m.ref_id source_id,m.notes,m.reversal_of_movement_id reversal
            FROM inv_stock_movement_log m LEFT JOIN inv_stock_movement_log o ON o.id=m.reversal_of_movement_id
            LEFT JOIN mst_material a ON a.id=m.material_id LEFT JOIN mst_uom u ON u.id=m.content_uom_id
            WHERE m.movement_scope='DIVISION' AND m.material_id IS NOT NULL AND m.movement_date BETWEEN ? AND ?) x
            WHERE loss_kind IS NOT NULL ORDER BY event_date DESC,movement_no LIMIT 50001",[$from,$to]);
        $component=$this->rows("SELECT 'COMPONENT' kind,m.movement_no,m.movement_date event_date,
            COALESCE(m.division_id,0) division_id,m.location_type destination,m.component_id asset_id,
            COALESCE(c.component_name,'Tanpa nama') asset_name,COALESCE(m.lot_no_snapshot,'') profile,'' brand,
            COALESCE(u.code,'-') unit,COALESCE(o.movement_type,m.movement_type) loss_kind,
            CASE COALESCE(o.movement_type,m.movement_type) WHEN 'WASTE' THEN al.waste_reason_code
                WHEN 'SPOIL' THEN al.spoil_reason_code ELSE al.adjustment_minus_reason_code END reason,
            m.qty_out-m.qty_in qty,(CASE WHEN m.reversal_of_movement_id IS NOT NULL THEN -1 ELSE 1 END)*m.total_cost value,
            m.unit_cost,m.source_table,m.source_id,m.notes,m.reversal_of_movement_id reversal
            FROM inv_component_movement_log m LEFT JOIN inv_component_movement_log o ON o.id=m.reversal_of_movement_id
            LEFT JOIN mst_component c ON c.id=m.component_id LEFT JOIN mst_uom u ON u.id=m.uom_id
            LEFT JOIN inv_component_adjustment_line al ON al.id=COALESCE(o.source_line_id,m.source_line_id)
                AND COALESCE(o.source_table,m.source_table)='inv_component_adjustment'
            WHERE m.movement_date BETWEEN ? AND ? AND COALESCE(o.movement_type,m.movement_type) IN ('WASTE','SPOIL','ADJUSTMENT_MINUS')
            ORDER BY m.movement_date DESC,m.id DESC LIMIT 50001",[$from,$to]);
        return array_merge($material,$component);
    }

    public function report(array $f): array
    {
        $f=Report_workspace::financialFilters($f);
        if($f['currency']!=='IDR'){throw new InvalidArgumentException('Analisis persediaan memakai nilai buku IDR. Pilih IDR, bukan konversi kurs perkiraan.');}
        $months=Report_workspace::financialMonths($f);$from=$f['month_from'].'-01';$to=min(date('Y-m-d'),Report_workspace::month($f['month_to'])[1]);
        $divisions=$this->divisions();$names=array_column($divisions,'name','id');
        if($f['division_id'] && !isset($names[$f['division_id']])){throw new InvalidArgumentException('Divisi tidak ditemukan.');}
        $groups=[];$warnings=[];
        $ensure=static function(string $month,int $division,string $location)use(&$groups,$names):string{
            $key=$month.'|'.$division.'|'.$location;
            if(!isset($groups[$key])){$groups[$key]=['key'=>$key,'month'=>$month,'division_id'=>$division,'division_name'=>$names[$division]??'Tanpa snapshot divisi',
                'location'=>$location,'revenue'=>0,'hpp'=>0,'purchase'=>0,'sr'=>0,'used'=>0,'transferred'=>0,'unvalued'=>0,'foreign'=>0,'material'=>null,'component'=>null];}
            return $key;
        };
        $revenue=Finance_division_revenue::read($this->db,$from,$to);
        if(!$revenue['available']){$warnings[]=$revenue['reason'];}
        if(empty($revenue['hpp_available'])){$warnings[]='Rincian HPP POS per divisi belum tersedia. Nilai HPP tidak ditampilkan sebagai nol.';}
        if($revenue['unallocated']){$warnings[]='Sebagian omzet tidak mempunyai snapshot divisi. Ditampilkan sebagai Tanpa snapshot divisi, bukan dibagikan secara tebakan.';}
        if(!empty($revenue['unallocated_hpp'])){$warnings[]='Sebagian HPP POS tidak mempunyai snapshot divisi dan ditampilkan sebagai Tanpa snapshot divisi.';}
        foreach($revenue['rows'] as $row){if(self::accepts($f,$row['division_id'],$row['location'])){$key=$ensure($row['month'],$row['division_id'],$row['location']);$groups[$key]['revenue']+=$row['revenue'];$groups[$key]['hpp']+=$row['hpp']??0;}}
        foreach(['MATERIAL','COMPONENT'] as $kind){
            $snapshots=$this->opnames($kind,date('Y-m-01',strtotime($from.' -1 month')),$to);
            foreach($this->stocks($kind,$from,$to) as $row){
                $division=(int)$row['division_id'];$location=self::location($row['destination']);
                if(!self::accepts($f,$division,$location)){continue;}
                $key=$ensure($row['month'],$division,$location);$field=strtolower($kind);
                if(!$groups[$key][$field]){$groups[$key][$field]=array_fill_keys(['opening','incoming','outgoing','closing','waste','spoil','process_loss','variance','plus','minus','profiles','negative_profiles'],0);}
                foreach(['opening','incoming','outgoing','closing','waste','spoil','process_loss','variance','plus','minus'] as $name){$groups[$key][$field][$name]+=Report_workspace::cents($row[$name]);}
                foreach(['profiles','negative_profiles'] as $name){$groups[$key][$field][$name]+=(int)$row[$name];}
                $prior=date('Y-m',strtotime($row['month'].'-01 -1 month')).'|'.$division.'|'.$location;
                $groups[$key][$field]['opname_before']=$snapshots[$prior]??null;
                $groups[$key][$field]['opname_after']=$snapshots[$key]??null;
            }
        }
        foreach((new Procurement_report_model())->materialFlowRows($from,$to) as $row){
            $division=(int)$row['division_id'];$location=self::location($row['destination']);if(!self::accepts($f,$division,$location)){continue;}
            $key=$ensure(substr($row['event_date'],0,7),$division,$location);
            if($row['currency']!=='IDR'){$groups[$key]['foreign']++;continue;}
            $groups[$key][$row['source']==='PURCHASE'?'purchase':'sr']+=Report_workspace::cents($row['value']);
            if((float)$row['qty_content']>0 && (float)$row['unit_cost']<=0){$groups[$key]['unvalued']++;}
        }
        foreach($this->materialUsage($from,$to) as $row){
            $division=(int)$row['division_id'];$location=self::location($row['destination']);if(!self::accepts($f,$division,$location)){continue;}
            $key=$ensure($row['month'],$division,$location);
            foreach(['used','transferred'] as $name){$groups[$key][$name]+=Report_workspace::cents($row[$name]);}
            $groups[$key]['unvalued']+=(int)$row['unvalued'];
        }
        foreach($groups as &$group){
            foreach(['material','component'] as $kind){
                if(!$group[$kind]){continue;}$stock=&$group[$kind];
                $stock['loss']=$stock['waste']+$stock['spoil'];
                $stock['reconciliation_gap']=$stock['opening']+$stock['incoming']+$stock['plus']-$stock['outgoing']-$stock['loss']-$stock['process_loss']-$stock['variance']-$stock['minus']-$stock['closing'];
                $stock['opening_gap']=$stock['opname_before']?$stock['opening']-$stock['opname_before']['value']:null;
                $stock['closing_gap']=$stock['opname_after']?$stock['closing']-$stock['opname_after']['value']:null;
                $stock['snapshot_incomplete']=$stock['opname_after'] && $stock['opname_after']['profiles']!==$stock['profiles'];
                unset($stock);
            }
            $group['supply']=$group['purchase']+$group['sr'];
            $group['supply_ratio']=$revenue['available'] && $group['revenue']>0?100*$group['supply']/$group['revenue']:null;
            $group['hpp_ratio']=!empty($revenue['hpp_available']) && $group['revenue']>0?100*$group['hpp']/$group['revenue']:null;
            $stock=$group['material'];
            $group['usage_gap']=$stock?$stock['outgoing']-$group['used']-$group['transferred']:null;
            $group['usage_reliable']=$stock && !$stock['negative_profiles'] && !$group['unvalued'] && !$group['foreign']
                && abs($stock['reconciliation_gap'])<=100 && abs($group['usage_gap'])<=100
                && ($stock['opening_gap']===null || abs($stock['opening_gap'])<=100)
                && ($stock['closing_gap']===null || abs($stock['closing_gap'])<=100)
                && !$stock['snapshot_incomplete'] && empty($stock['opname_before']['negative_profiles'])
                && empty($stock['opname_after']['negative_profiles']) && $group['used']>=0;
            // Keep the observed ratio visible. Reliability is reported
            // separately so a stock anomaly does not erase useful analysis.
            $group['usage_ratio']=$revenue['available'] && $group['revenue']>0 && $group['used']>=0?100*$group['used']/$group['revenue']:null;
        }unset($group);
        usort($groups,static fn($a,$b)=>strcmp($b['month'],$a['month']) ?: (($a['location']==='REGULAR'?0:1)<=>($b['location']==='REGULAR'?0:1)) ?: strcmp($a['division_name'],$b['division_name']));
        $monthly=[];foreach($months as $month){$monthly[$month]=['label'=>$month,'revenue'=>$revenue['available']?0:null,'hpp'=>!empty($revenue['hpp_available'])?0:null,'supply'=>0,'used'=>0,'waste'=>0,'spoil'=>0,'process_loss'=>0,'correction'=>0];}
        foreach($groups as $group){foreach(['revenue','hpp','supply','used'] as $field){if($monthly[$group['month']][$field]!==null){$monthly[$group['month']][$field]+=$group[$field];}}}
        $losses=[];$rank=[];$reasons=[];$lossTotals=['waste'=>0,'spoil'=>0,'process_loss'=>0,'correction'=>0,'material'=>0,'component'=>0,'unvalued'=>0,'reversals'=>0];
        foreach($this->lossRows($from,$to) as $row){
            $division=(int)$row['division_id'];$location=self::location($row['destination']);if(!self::accepts($f,$division,$location)){continue;}
            if($f['inventory_kind']!=='ALL' && $row['kind']!==$f['inventory_kind']){continue;}
            if($f['loss_kind']!=='ALL' && $row['loss_kind']!==$f['loss_kind']){continue;}
            if($f['asset_q']!=='' && stripos(implode(' ',[$row['asset_name'],$row['profile'],$row['brand'],$row['reason'],$row['movement_no']]),$f['asset_q'])===false){continue;}
            $row['month']=substr($row['event_date'],0,7);$row['division_name']=$names[$division]??'Tanpa divisi';$row['location']=$location;
            $row['value_cents']=Report_workspace::cents($row['value']);
            $bucket=in_array($row['loss_kind'],['WASTE','SPOIL','PROCESS_LOSS'],true)?strtolower($row['loss_kind']):'correction';
            $monthly[$row['month']][$bucket]+=$row['value_cents'];
            if($row['month']!==$f['month']){continue;}
            $losses[]=$row;$lossTotals[$bucket]+=$row['value_cents'];
            if(in_array($bucket,['waste','spoil'],true)){$lossTotals[strtolower($row['kind'])]+=$row['value_cents'];}
            $lossTotals['unvalued']+=(float)$row['qty']!=0 && (float)$row['unit_cost']<=0?1:0;
            $lossTotals['reversals']+=!empty($row['reversal'])?1:0;
            $key=implode('|',[$row['kind'],$row['asset_id'],$row['profile'],$division,$location,$row['loss_kind'],$row['unit']]);
            if(!isset($rank[$key])){$rank[$key]=array_replace($row,['value_cents'=>0,'qty'=>0.,'count'=>0]);}
            $rank[$key]['value_cents']+=$row['value_cents'];$rank[$key]['qty']+=(float)$row['qty'];$rank[$key]['count']++;
            $reason=$row['loss_kind'].' / '.($row['reason']?:'Alasan belum diisi');$reasons[$reason]=($reasons[$reason]??0)+$row['value_cents'];
        }
        usort($losses,static fn($a,$b)=>strcmp($b['event_date'],$a['event_date']) ?: strcmp($b['movement_no'],$a['movement_no']));
        uasort($rank,static fn($a,$b)=>$b['value_cents']<=>$a['value_cents']);arsort($reasons);
        return ['groups'=>$groups,'focus_groups'=>array_values(array_filter($groups,static fn($g)=>$g['month']===$f['month'])),
            'monthly'=>array_values($monthly),'divisions'=>$divisions,'warnings'=>$warnings,'revenue_available'=>$revenue['available'],
            'hpp_available'=>!empty($revenue['hpp_available']),'losses'=>$losses,'loss_pager'=>Report_workspace::page($losses,$f),
            'rankings'=>array_values($rank),'reasons'=>$reasons,'loss_totals'=>$lossTotals];
    }
}

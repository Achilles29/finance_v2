<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once __DIR__.'/Report_workspace.php';

/** Reconciled allocation of POS revenue and sale HPP to saved POS divisions.
 * Does not infer revenue or HPP from current recipes or stock issues. */
class Finance_division_revenue
{
    public static function allocate(int $amount, array $weights): array
    {
        $weights=array_filter($weights,static fn($v)=>$v>0);
        if(!$weights){return [0=>$amount];}
        ksort($weights); $sum=array_sum($weights); $result=[]; $fractions=[]; $remaining=abs($amount);
        foreach($weights as $key=>$weight){
            $exact=abs($amount)*$weight/$sum; $base=(int)floor($exact);
            $result[$key]=$base; $remaining-=$base; $fractions[$key]=$exact-$base;
        }
        arsort($fractions,SORT_NUMERIC);
        foreach(array_keys($fractions) as $key){if($remaining--<=0){break;}$result[$key]++;}
        if($amount<0){foreach($result as &$value){$value=-$value;}unset($value);}
        return $result;
    }

    public static function read($db, string $from, string $to): array
    {
        $query=static function(string $sql,array $binds=[])use($db):array{
            $q=$db->query($sql,$binds);if(!$q){throw new RuntimeException('Alokasi omzet divisi tidak dapat dibaca.');}
            $rows=$q->result_array();if(count($rows)>50000){throw new InvalidArgumentException('Data alokasi omzet terlalu besar. Persempit rentang.');}return $rows;
        };
        foreach(['pos_order','pos_order_line','pos_order_line_extra','pos_refund','pos_refund_line','mst_product_division'] as $table){
            if(!$db->table_exists($table)){return ['available'=>false,'rows'=>[],'unallocated'=>0,'reason'=>'Snapshot rincian POS belum tersedia.'];}
        }
        $end=date('Y-m-d',strtotime($to.' +1 day'));
        $scope="o.paid_at IS NOT NULL AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL')
            AND ((o.paid_at>=? AND o.paid_at<?) OR EXISTS(SELECT 1 FROM pos_refund rr WHERE rr.order_id=o.id
                AND rr.refund_status='POSTED' AND rr.refunded_at>=? AND rr.refunded_at<?))";
        $binds=[$from,$end,$from,$end];
        // Line balances lose the pre-promotion goods value, not cash refunded.
        // Use the same legacy fallback as the POS sales report.
        $grossRefund=$db->field_exists('gross_amount_refunded','pos_refund_line')
            ? 'COALESCE(NULLIF(rl.gross_amount_refunded,0),rl.amount_refunded,0)'
            : 'COALESCE(rl.amount_refunded,0)';
        $orders=$query("SELECT o.id,DATE(o.paid_at) sale_date,o.order_scope,
            CASE WHEN COALESCE(rf.amount,0)>0 AND o.paid_total>0 THEN o.paid_total ELSE o.grand_total END-o.tax_amount sale_value
            FROM pos_order o LEFT JOIN (SELECT order_id,SUM(refund_amount) amount FROM pos_refund
                WHERE refund_status='POSTED' GROUP BY order_id) rf ON rf.order_id=o.id WHERE $scope LIMIT 50001",$binds);
        $weights=[];
        $scopeKey="CONCAT(COALESCE(l.operational_division_id,0),'|',CASE WHEN pd.pos_scope IN ('REGULAR','EVENT') THEN pd.pos_scope ELSE o.order_scope END)";
        $lines=$query("SELECT l.order_id,$scopeKey scope_key,
            l.net_amount+COALESCE(r.amount,0) weight
            FROM pos_order_line l JOIN pos_order o ON o.id=l.order_id
            LEFT JOIN mst_product_division pd ON pd.id=l.product_division_id_snapshot
            LEFT JOIN (SELECT rl.order_line_id,SUM($grossRefund) amount FROM pos_refund_line rl
                JOIN pos_refund r ON r.id=rl.refund_id WHERE r.refund_status='POSTED' AND rl.line_type='PRODUCT'
                GROUP BY rl.order_line_id) r ON r.order_line_id=l.id
            WHERE $scope AND l.line_status!='VOID' AND l.line_type!='BUNDLE_HEADER' LIMIT 50001",$binds);
        $extras=$query("SELECT e.order_id,$scopeKey scope_key,e.net_amount+COALESCE(r.amount,0) weight
            FROM pos_order_line_extra e JOIN pos_order_line l ON l.id=e.order_line_id JOIN pos_order o ON o.id=e.order_id
            LEFT JOIN mst_product_division pd ON pd.id=l.product_division_id_snapshot
            LEFT JOIN (SELECT rl.order_extra_line_id,SUM($grossRefund) amount FROM pos_refund_line rl
                JOIN pos_refund r ON r.id=rl.refund_id WHERE r.refund_status='POSTED' AND rl.line_type='EXTRA'
                GROUP BY rl.order_extra_line_id) r ON r.order_extra_line_id=e.id
            WHERE $scope AND l.line_status!='VOID' AND l.line_type!='BUNDLE_HEADER' LIMIT 50001",$binds);
        foreach(array_merge($lines,$extras) as $line){
            $id=(int)$line['order_id'];$key=$line['scope_key'];
            $weights[$id][$key]=($weights[$id][$key]??0)+max(0,Report_workspace::cents($line['weight']));
        }
        $refunds=$query("SELECT r.id,r.order_id,DATE(r.refunded_at) event_date,o.order_scope,r.refund_amount
            FROM pos_refund r JOIN pos_order o ON o.id=r.order_id WHERE r.refund_status='POSTED'
                AND r.refunded_at>=? AND r.refunded_at<? AND o.paid_at IS NOT NULL
                AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL') LIMIT 50001",[$from,$end]);
        $refundWeights=[];
        $refundLines=$query("SELECT rl.refund_id,CONCAT(COALESCE(l.operational_division_id,el.operational_division_id,0),'|',
                CASE WHEN pd.pos_scope IN ('REGULAR','EVENT') THEN pd.pos_scope ELSE o.order_scope END) scope_key,rl.amount_refunded
            FROM pos_refund_line rl JOIN pos_refund r ON r.id=rl.refund_id JOIN pos_order o ON o.id=r.order_id
            LEFT JOIN pos_order_line l ON l.id=rl.order_line_id AND rl.line_type='PRODUCT'
            LEFT JOIN pos_order_line_extra e ON e.id=rl.order_extra_line_id AND rl.line_type='EXTRA'
            LEFT JOIN pos_order_line el ON el.id=e.order_line_id
            LEFT JOIN mst_product_division pd ON pd.id=COALESCE(l.product_division_id_snapshot,el.product_division_id_snapshot)
            WHERE r.refund_status='POSTED' AND r.refunded_at>=? AND r.refunded_at<? LIMIT 50001",[$from,$end]);
        foreach($refundLines as $line){$id=(int)$line['refund_id'];$key=$line['scope_key'];$refundWeights[$id][$key]=($refundWeights[$id][$key]??0)+max(0,Report_workspace::cents($line['amount_refunded']));}
        $rows=[];$unallocated=0;$unallocatedHpp=0;
        $add=static function(string $date,string $fallbackLocation,array $amounts,string $metric='revenue')use(&$rows,&$unallocated,&$unallocatedHpp):void{
            foreach($amounts as $scope=>$amount){
                [$division,$location]=array_pad(explode('|',(string)$scope),2,$fallbackLocation);$division=(int)$division;
                $month=substr($date,0,7);$key=$month.'|'.$division.'|'.$location;
                if(!isset($rows[$key])){$rows[$key]=['month'=>$month,'division_id'=>(int)$division,'location'=>$location,'revenue'=>0,'hpp'=>0];}
                $rows[$key][$metric]+=$amount;
                if(!$division){if($metric==='revenue'){$unallocated+=$amount;}else{$unallocatedHpp+=$amount;}}
            }
        };
        foreach($orders as $order){if($order['sale_date']>=$from && $order['sale_date']<=$to){$add($order['sale_date'],$order['order_scope'],self::allocate(Report_workspace::cents($order['sale_value']),$weights[$order['id']]??[]));}}
        // A refund without a division snapshot stays unallocated, never guessed.
        foreach($refunds as $refund){$add($refund['event_date'],$refund['order_scope'],self::allocate(-Report_workspace::cents($refund['refund_amount']),$refundWeights[$refund['id']]??[]));}

        $hppAvailable=$db->field_exists('cogs_amount','pos_order_line') && $db->field_exists('cost_amount_snapshot','pos_order_line_extra')
            && $db->field_exists('cost_reversed','pos_refund_line');
        if($hppAvailable){
            $saleScope="o.paid_at>=? AND o.paid_at<? AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL')";
            $extrasHpp='(SELECT order_line_id,SUM(COALESCE(qty,0)*COALESCE(cost_amount_snapshot,0)) amount FROM pos_order_line_extra GROUP BY order_line_id)';
            $refundHpp='(SELECT rl.order_line_id,SUM(rl.cost_reversed) amount FROM pos_refund_line rl JOIN pos_refund r ON r.id=rl.refund_id WHERE r.refund_status=\'POSTED\' AND rl.order_line_id IS NOT NULL GROUP BY rl.order_line_id)';
            $saleHpp=$query("SELECT DATE(o.paid_at) event_date,o.order_scope,$scopeKey scope_key,
                SUM(COALESCE(l.cogs_amount,0)+COALESCE(x.amount,0)+COALESCE(r.amount,0)) amount
                FROM pos_order_line l JOIN pos_order o ON o.id=l.order_id
                LEFT JOIN mst_product_division pd ON pd.id=l.product_division_id_snapshot
                LEFT JOIN $extrasHpp x ON x.order_line_id=l.id LEFT JOIN $refundHpp r ON r.order_line_id=l.id
                WHERE $saleScope AND l.line_status!='VOID' GROUP BY event_date,o.order_scope,scope_key",[$from,$end]);
            foreach($saleHpp as $row){$add($row['event_date'],$row['order_scope'],[$row['scope_key']=>Report_workspace::cents($row['amount'])],'hpp');}

            $refundHppRows=$query("SELECT DATE(r.refunded_at) event_date,o.order_scope,
                CONCAT(COALESCE(l.operational_division_id,el.operational_division_id,0),'|',
                    CASE WHEN pd.pos_scope IN ('REGULAR','EVENT') THEN pd.pos_scope ELSE o.order_scope END) scope_key,
                SUM(rl.cost_reversed) amount
                FROM pos_refund_line rl JOIN pos_refund r ON r.id=rl.refund_id JOIN pos_order o ON o.id=r.order_id
                LEFT JOIN pos_order_line l ON l.id=rl.order_line_id AND rl.line_type='PRODUCT'
                LEFT JOIN pos_order_line_extra e ON e.id=rl.order_extra_line_id AND rl.line_type='EXTRA'
                LEFT JOIN pos_order_line el ON el.id=e.order_line_id
                LEFT JOIN mst_product_division pd ON pd.id=COALESCE(l.product_division_id_snapshot,el.product_division_id_snapshot)
                WHERE r.refund_status='POSTED' AND r.refunded_at>=? AND r.refunded_at<?
                    AND o.paid_at IS NOT NULL AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL')
                GROUP BY event_date,o.order_scope,scope_key",[$from,$end]);
            foreach($refundHppRows as $row){$add($row['event_date'],$row['order_scope'],[$row['scope_key']=>-Report_workspace::cents($row['amount'])],'hpp');}

            if($db->table_exists('inv_stock_deficit_cogs_adjustment')
                && $db->field_exists('status','inv_stock_deficit_cogs_adjustment')
                && $db->field_exists('order_id','inv_stock_deficit_cogs_adjustment')
                && $db->field_exists('order_line_id','inv_stock_deficit_cogs_adjustment')
                && $db->field_exists('variance_amount','inv_stock_deficit_cogs_adjustment')
                && $db->field_exists('recognition_date','inv_stock_deficit_cogs_adjustment')
                && $db->field_exists('operational_division_id','inv_stock_deficit_cogs_adjustment')){
                $adjustmentScope="CONCAT(COALESCE(a.operational_division_id,l.operational_division_id,0),'|',
                    CASE WHEN pd.pos_scope IN ('REGULAR','EVENT') THEN pd.pos_scope ELSE o.order_scope END)";
                $adjustments=$query("SELECT a.recognition_date event_date,o.order_scope,$adjustmentScope scope_key,SUM(a.variance_amount) amount
                    FROM inv_stock_deficit_cogs_adjustment a JOIN pos_order o ON o.id=a.order_id
                    LEFT JOIN pos_order_line l ON l.id=a.order_line_id
                    LEFT JOIN mst_product_division pd ON pd.id=l.product_division_id_snapshot
                    WHERE a.status='POSTED' AND o.paid_at IS NOT NULL AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL')
                        AND a.recognition_date>=? AND a.recognition_date<?
                    GROUP BY event_date,o.order_scope,scope_key",[$from,$end]);
                foreach($adjustments as $row){$add($row['event_date'],$row['order_scope'],[$row['scope_key']=>Report_workspace::cents($row['amount'])],'hpp');}
                if($db->table_exists('inv_stock_deficit_cogs_reversal') && $db->field_exists('variance_amount_reversed','inv_stock_deficit_cogs_reversal')){
                    $reversals=$query("SELECT r.reversal_date event_date,o.order_scope,$adjustmentScope scope_key,SUM(r.variance_amount_reversed) amount
                        FROM inv_stock_deficit_cogs_reversal r JOIN inv_stock_deficit_cogs_adjustment a ON a.id=r.cogs_adjustment_id
                        JOIN pos_order o ON o.id=a.order_id LEFT JOIN pos_order_line l ON l.id=a.order_line_id
                        LEFT JOIN mst_product_division pd ON pd.id=l.product_division_id_snapshot
                        WHERE a.status='POSTED' AND o.paid_at IS NOT NULL AND o.status NOT IN ('DRAFT','PENDING','VOID','PAID_PARTIAL')
                            AND r.reversal_date>=? AND r.reversal_date<?
                        GROUP BY event_date,o.order_scope,scope_key",[$from,$end]);
                    foreach($reversals as $row){$add($row['event_date'],$row['order_scope'],[$row['scope_key']=>-Report_workspace::cents($row['amount'])],'hpp');}
                }
            }
        }
        return ['available'=>true,'hpp_available'=>$hppAvailable,'rows'=>array_values($rows),'unallocated'=>$unallocated,
            'unallocated_hpp'=>$unallocatedHpp,'reason'=>''];
    }
}

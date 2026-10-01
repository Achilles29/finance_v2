<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Assets extends MY_Controller
{
    private const PAGE_ITEM = 'asset.item.index';
    private const PAGE_DAMAGE = 'asset.damage.index';
    private const PAGE_RECON = 'asset.recon.index';
    private const PAGE_TRANSFER = 'asset.transfer.index';
    private const PAGE_LABEL = 'asset.label.index';
    private const PAGE_MAINTENANCE = 'asset.maintenance.index';
    private const PAGE_HANDOVER = 'asset.handover.index';
    private const PAGE_DISPOSAL = 'asset.disposal.index';
    private const PAGE_DEPRECIATION = 'asset.depreciation.index';
    private const PAGE_MASTER_CHANGE = 'asset.master_change.index';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Asset_model');
        $this->load->helper(['url', 'form']);
    }

    public function index()
    {
        $this->require_permission(self::PAGE_ITEM, 'view');

        $filters = $this->asset_filters();
        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Asset_model->count_asset_groups($filters);
        $pg = $this->build_pagination($total, $perPage, $page);

        $this->render('assets/index', [
            'page_title' => 'Pengelolaan Aset',
            'active_menu' => 'asset.item',
            'filters' => $filters,
            'pg' => $pg,
            'rows' => $this->Asset_model->list_asset_groups($filters, $pg['per_page'], $pg['offset']),
            'summary' => $this->Asset_model->asset_summary($filters),
            'categories' => $this->Asset_model->category_options(false),
            'divisions' => $this->Asset_model->division_options(),
            'status_labels' => $this->Asset_model->status_labels(),
            'table_ready' => $this->Asset_model->table_ready(),
            'master_lock_ready' => $this->Asset_model->master_lock_ready(),
            'can_create' => $this->can(self::PAGE_ITEM, 'create'),
            'can_edit' => $this->can(self::PAGE_ITEM, 'edit'),
            'can_lock' => $this->can(self::PAGE_ITEM, 'edit'),
            'can_delete' => $this->can(self::PAGE_ITEM, 'delete'),
            'can_damage' => $this->can(self::PAGE_DAMAGE, 'create'),
        ]);
    }

    public function create()
    {
        $this->require_permission(self::PAGE_ITEM, 'create');
        $this->render_form(null);
    }

    public function store()
    {
        $this->require_permission(self::PAGE_ITEM, 'create');
        if (!$this->ensure_asset_storage_ready(['uploads/assets/photos'])) {
            redirect('asset-management/create');
            return;
        }

        $payload = $this->asset_payload_from_post();
        if (!($payload['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)$payload['message']);
            redirect('asset-management/create');
            return;
        }

        $photo = $this->handle_image_upload('asset_photo', 'uploads/assets/photos', 'photo');
        if ($photo === false) {
            redirect('asset-management/create');
            return;
        }

        $qty = max(1, min(500, (int)$this->input->post('quantity', true)));
        $serials = $this->serial_numbers_from_post();
        $result = $this->Asset_model->save_bulk($payload['data'], $serials, $photo, $qty, $this->actor_user_id());
        if (!($result['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal menyimpan aset.'));
            redirect('asset-management/create');
            return;
        }

        $this->session->set_flashdata('success', 'Berhasil menambahkan ' . (int)$result['count'] . ' unit aset. Setiap unit sudah mendapat kode unik.');
        redirect('asset-management');
    }

    public function edit($id)
    {
        $this->require_permission(self::PAGE_ITEM, 'edit');
        $asset = $this->Asset_model->find_asset((int)$id);
        if (!$asset) {
            show_404();
            return;
        }
        if (!$this->ensure_asset_in_scope($asset, 'asset-management')) {
            return;
        }
        if ($this->Asset_model->master_lock_ready() && !empty($asset['is_master_locked'])) {
            $this->session->set_flashdata('error', 'Data awal aset sudah dikunci. Ajukan perubahan data agar riwayat tetap jelas.');
            redirect($this->can(self::PAGE_MASTER_CHANGE, 'create') ? 'asset-management/changes/create/' . (int)$asset['id'] : 'asset-management/detail/' . (int)$asset['id']);
            return;
        }
        $this->render_form($asset);
    }

    public function update($id)
    {
        $this->require_permission(self::PAGE_ITEM, 'edit');
        $id = (int)$id;
        $asset = $this->Asset_model->find_asset($id);
        if (!$asset) {
            show_404();
            return;
        }
        if (!$this->ensure_asset_in_scope($asset, 'asset-management')) {
            return;
        }
        if ($this->Asset_model->master_lock_ready() && !empty($asset['is_master_locked'])) {
            $this->session->set_flashdata('error', 'Data awal aset sudah dikunci. Ajukan perubahan data agar riwayat tetap jelas.');
            redirect($this->can(self::PAGE_MASTER_CHANGE, 'create') ? 'asset-management/changes/create/' . $id : 'asset-management/detail/' . $id);
            return;
        }

        $payload = $this->asset_payload_from_post(true);
        if (!($payload['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)$payload['message']);
            redirect('asset-management/edit/' . $id);
            return;
        }

        $photo = $this->handle_image_upload('asset_photo', 'uploads/assets/photos', 'photo');
        if ($photo === false) {
            redirect('asset-management/edit/' . $id);
            return;
        }

        $result = $this->Asset_model->update_asset($id, $payload['data'], $photo, $this->actor_user_id());
        if (!($result['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal mengupdate aset.'));
            redirect('asset-management/edit/' . $id);
            return;
        }

        $this->session->set_flashdata('success', 'Aset berhasil diupdate.');
        redirect('asset-management/detail/' . $id);
    }

    /** Hapus hanya untuk unit yang masih OPEN dan belum punya jejak operasional. */
    public function delete($id)
    {
        $this->require_permission(self::PAGE_ITEM, 'delete');
        $asset = $this->Asset_model->find_asset((int)$id);
        if (!$asset) {
            show_404();
            return;
        }
        if (!$this->ensure_asset_in_scope($asset, 'asset-management')) {
            return;
        }

        $result = $this->Asset_model->delete_open_asset((int)$asset['id'], $this->active_division_id());
        if (empty($result['ok'])) {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Aset gagal dihapus.'));
            redirect('asset-management/detail/' . (int)$asset['id']);
            return;
        }

        $groupKey = strtolower(trim((string)($result['group_key'] ?? '')));
        $redirect = 'asset-management';
        if (preg_match('/^[a-f0-9]{40}$/', $groupKey)) {
            $scopeId = $this->active_division_id();
            $group = $this->Asset_model->find_asset_group($groupKey, [
                'q' => '',
                'status' => 'ALL',
                'division_id' => $scopeId ?? 0,
                'division_scope_id' => $scopeId ?? 0,
            ]);
            if ($group) {
                $redirect = $this->asset_group_url($groupKey, $scopeId);
            }
        }

        $this->session->set_flashdata('success', 'Unit aset ' . (string)($result['asset_code'] ?? '') . ' berhasil dihapus.');
        redirect($redirect);
    }

    public function detail($id)
    {
        $this->require_permission(self::PAGE_ITEM, 'view');
        $asset = $this->Asset_model->find_asset((int)$id);
        if (!$asset) {
            show_404();
            return;
        }
        if (!$this->ensure_asset_in_scope($asset, 'asset-management')) {
            return;
        }

        $this->render('assets/detail', [
            'page_title' => 'Detail Aset',
            'active_menu' => 'asset.item',
            'asset' => $asset,
            'events' => $this->Asset_model->asset_events((int)$id),
            'can_edit' => $this->can(self::PAGE_ITEM, 'edit'),
            'can_delete' => $this->can(self::PAGE_ITEM, 'delete'),
            'can_lock' => $this->can(self::PAGE_ITEM, 'edit'),
            'can_change_create' => $this->can(self::PAGE_MASTER_CHANGE, 'create'),
            'master_lock_ready' => $this->Asset_model->master_lock_ready(),
            'can_damage' => $this->can(self::PAGE_DAMAGE, 'create'),
        ]);
    }

    public function group($groupKey)
    {
        $this->require_permission(self::PAGE_ITEM, 'view');

        $groupKey = strtolower(trim((string)$groupKey));
        if (!preg_match('/^[a-f0-9]{40}$/', $groupKey)) {
            show_404();
            return;
        }

        $filters = $this->asset_filters();
        $filters['group_key'] = $groupKey;

        $groupFilters = $filters;
        $groupFilters['status'] = 'ALL';
        $groupFilters['q'] = '';
        $group = $this->Asset_model->find_asset_group($groupKey, $groupFilters);
        if (!$group) {
            show_404();
            return;
        }

        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Asset_model->count_assets($filters);
        $pg = $this->build_pagination($total, $perPage, $page);

        $this->render('assets/group_detail', [
            'page_title' => 'Detail Grup Aset',
            'active_menu' => 'asset.item',
            'filters' => $filters,
            'pg' => $pg,
            'group' => $group,
            'rows' => $this->Asset_model->list_assets($filters, $pg['per_page'], $pg['offset']),
            'divisions' => $this->Asset_model->division_options(),
            'status_labels' => $this->Asset_model->status_labels(),
            'can_create' => $this->can(self::PAGE_ITEM, 'create'),
            'can_edit' => $this->can(self::PAGE_ITEM, 'edit'),
            'can_delete' => $this->can(self::PAGE_ITEM, 'delete'),
            'can_lock' => $this->can(self::PAGE_ITEM, 'edit'),
            'can_change_create' => $this->can(self::PAGE_MASTER_CHANGE, 'create'),
            'master_lock_ready' => $this->Asset_model->master_lock_ready(),
            'can_damage' => $this->can(self::PAGE_DAMAGE, 'create'),
        ]);
    }

    /** Menyesuaikan jumlah unit dalam satu grup, tanpa menyentuh unit terkunci. */
    public function group_quantity($groupKey)
    {
        $this->require_permission(self::PAGE_ITEM, 'edit');

        $groupKey = strtolower(trim((string)$groupKey));
        if (!preg_match('/^[a-f0-9]{40}$/', $groupKey)) {
            show_404();
            return;
        }

        $scopeId = $this->active_division_id();
        $divisionId = (int)$this->input->post('division_id', true);
        if ($scopeId !== null) {
            $divisionId = $scopeId;
        }
        $redirect = $this->asset_group_url($groupKey, $divisionId > 0 ? $divisionId : null);

        $rawTarget = trim((string)$this->input->post('target_quantity', true));
        if ($rawTarget === '' || !preg_match('/^\d+$/', $rawTarget)) {
            $this->session->set_flashdata('error', 'Jumlah aset harus berupa bilangan bulat nol atau lebih.');
            redirect($redirect);
            return;
        }
        $targetQuantity = (int)$rawTarget;

        $group = $this->Asset_model->find_asset_group($groupKey, [
            'q' => '',
            'status' => 'ALL',
            'division_id' => $divisionId,
            'division_scope_id' => $scopeId ?? 0,
        ]);
        if (!$group) {
            show_404();
            return;
        }

        $currentQuantity = (int)($group['unit_count'] ?? 0);
        if ($targetQuantity > $currentQuantity && !$this->can(self::PAGE_ITEM, 'create')) {
            $this->session->set_flashdata('error', 'Anda tidak memiliki hak untuk menambah unit aset.');
            redirect($redirect);
            return;
        }
        if ($targetQuantity < $currentQuantity && !$this->can(self::PAGE_ITEM, 'delete')) {
            $this->session->set_flashdata('error', 'Anda tidak memiliki hak untuk mengurangi unit aset.');
            redirect($redirect);
            return;
        }

        $result = $this->Asset_model->adjust_group_quantity(
            $groupKey,
            $targetQuantity,
            $this->serial_numbers_from_post(),
            $this->actor_user_id(),
            $divisionId > 0 ? $divisionId : null,
            $scopeId
        );
        if (empty($result['ok'])) {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Jumlah aset gagal disesuaikan.'));
            redirect($redirect);
            return;
        }

        $created = (int)($result['created'] ?? 0);
        $deleted = (int)($result['deleted'] ?? 0);
        if ($created > 0) {
            $message = $created . ' unit aset ditambahkan. Total sekarang ' . (int)($result['quantity'] ?? $targetQuantity) . ' unit.';
        } elseif ($deleted > 0) {
            $message = $deleted . ' unit pendataan dihapus. Total sekarang ' . (int)($result['quantity'] ?? $targetQuantity) . ' unit.';
        } else {
            $message = 'Jumlah aset sudah sesuai: ' . (int)($result['quantity'] ?? $targetQuantity) . ' unit.';
        }
        $this->session->set_flashdata('success', $message);
        redirect($targetQuantity === 0 ? 'asset-management' : $redirect);
    }

    /** Mengunci satu atau banyak data awal aset tanpa mengubah kondisi fisiknya. */
    public function lock_bulk()
    {
        $this->require_permission(self::PAGE_ITEM, 'edit');
        if (!$this->Asset_model->master_lock_ready()) {
            $this->session->set_flashdata('error', 'Fitur kunci aset belum siap. Jalankan migration asset master lock terlebih dahulu.');
            redirect('asset-management');
            return;
        }

        $scopeId = $this->active_division_id();
        $assetIds = (array)$this->input->post('asset_ids');
        $groupKeys = (array)$this->input->post('group_keys');
        $locked = 0;
        $messages = [];

        if (!empty($groupKeys)) {
            $result = $this->Asset_model->lock_asset_groups($groupKeys, $this->actor_user_id(), $scopeId);
            if (!empty($result['ok'])) {
                $locked += (int)($result['locked'] ?? 0);
            } else {
                $messages[] = (string)($result['message'] ?? 'Produk aset gagal dikunci.');
            }
        }
        if (!empty($assetIds)) {
            $result = $this->Asset_model->lock_assets($assetIds, $this->actor_user_id(), $scopeId);
            if (!empty($result['ok'])) {
                $locked += (int)($result['locked'] ?? 0);
            } else {
                $messages[] = (string)($result['message'] ?? 'Unit aset gagal dikunci.');
            }
        }

        if ($locked > 0) {
            $this->session->set_flashdata('success', $locked . ' unit aset sudah dikunci. Setiap perubahan data awal berikutnya harus melalui pengajuan perubahan data aset.');
        } else {
            $this->session->set_flashdata('error', !empty($messages) ? implode(' ', $messages) : 'Pilih unit atau produk aset yang ingin dikunci.');
        }
        $back = trim((string)$this->input->post('back_url', true));
        redirect($back !== '' ? $back : 'asset-management');
    }

    public function lock_asset($id)
    {
        $this->require_permission(self::PAGE_ITEM, 'edit');
        $asset = $this->Asset_model->find_asset((int)$id);
        if (!$asset) {
            show_404();
            return;
        }
        if (!$this->ensure_asset_in_scope($asset, 'asset-management')) {
            return;
        }

        $result = $this->Asset_model->lock_assets([(int)$asset['id']], $this->actor_user_id(), $this->active_division_id());
        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', !empty($result['ok']) ? 'Data awal aset sudah dikunci.' : (string)($result['message'] ?? 'Gagal mengunci aset.'));
        redirect('asset-management/detail/' . (int)$asset['id']);
    }

    public function changes()
    {
        $this->require_permission(self::PAGE_MASTER_CHANGE, 'view');
        $filters = $this->master_change_filters();
        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Asset_model->count_master_change_requests($filters);
        $pg = $this->build_pagination($total, $perPage, $page);

        $this->render('assets/change_index', [
            'page_title' => 'Perubahan Data Aset',
            'active_menu' => 'asset.master_change',
            'asset_nav_active' => 'changes',
            'filters' => $filters,
            'pg' => $pg,
            'rows' => $this->Asset_model->list_master_change_requests($filters, $pg['per_page'], $pg['offset']),
            'divisions' => $this->Asset_model->division_options(),
            'status_labels' => $this->Asset_model->master_change_status_labels(),
            'master_lock_ready' => $this->Asset_model->master_lock_ready(),
            'can_create' => $this->can(self::PAGE_MASTER_CHANGE, 'create'),
            'can_edit' => $this->can(self::PAGE_MASTER_CHANGE, 'edit'),
            'can_delete' => $this->can(self::PAGE_MASTER_CHANGE, 'delete'),
        ]);
    }

    public function change_create($assetId)
    {
        $this->require_permission(self::PAGE_MASTER_CHANGE, 'create');
        $asset = $this->Asset_model->find_asset((int)$assetId);
        if (!$asset) {
            show_404();
            return;
        }
        if (!$this->ensure_asset_in_scope($asset, 'asset-management/changes')) {
            return;
        }
        if (!$this->Asset_model->master_lock_ready() || empty($asset['is_master_locked'])) {
            $this->session->set_flashdata('error', 'Aset ini belum dikunci. Lengkapi data awal melalui Edit Aset terlebih dahulu.');
            redirect('asset-management/detail/' . (int)$asset['id']);
            return;
        }
        $this->render_master_change_form($asset);
    }

    public function change_store($assetId)
    {
        $this->require_permission(self::PAGE_MASTER_CHANGE, 'create');
        $asset = $this->Asset_model->find_asset((int)$assetId);
        if (!$asset) {
            show_404();
            return;
        }
        if (!$this->ensure_asset_in_scope($asset, 'asset-management/changes')) {
            return;
        }
        if (!$this->Asset_model->master_lock_ready() || empty($asset['is_master_locked'])) {
            $this->session->set_flashdata('error', 'Aset ini belum dikunci sehingga belum membutuhkan pengajuan perubahan.');
            redirect('asset-management/detail/' . (int)$asset['id']);
            return;
        }

        $payload = $this->master_change_payload_from_post($asset);
        if (empty($payload['ok'])) {
            $this->session->set_flashdata('error', (string)($payload['message'] ?? 'Data pengajuan tidak valid.'));
            redirect('asset-management/changes/create/' . (int)$asset['id']);
            return;
        }

        $photo = $this->handle_image_upload('asset_photo', 'uploads/assets/photos', 'photo', false);
        if ($photo === false) {
            redirect('asset-management/changes/create/' . (int)$asset['id']);
            return;
        }
        if (!empty($photo['photo_path'])) {
            $payload['data']['photo_path'] = $photo['photo_path'];
            $payload['data']['photo_mime'] = $photo['photo_mime'] ?? null;
        }
        $evidence = $this->handle_image_upload('evidence_file', 'uploads/assets/evidence', 'evidence', false);
        if ($evidence === false) {
            redirect('asset-management/changes/create/' . (int)$asset['id']);
            return;
        }

        $result = $this->Asset_model->create_master_change_request(
            (int)$asset['id'],
            $payload['data'],
            (string)$payload['reason'],
            $evidence,
            $this->actor_user_id()
        );
        if (empty($result['ok'])) {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal mengirim pengajuan perubahan aset.'));
            redirect('asset-management/changes/create/' . (int)$asset['id']);
            return;
        }
        $this->session->set_flashdata('success', (string)$result['message']);
        redirect('asset-management/changes/' . (int)$result['id']);
    }

    public function change_detail($id)
    {
        $this->require_permission(self::PAGE_MASTER_CHANGE, 'view');
        $request = $this->Asset_model->find_master_change_request((int)$id);
        if (!$request) {
            show_404();
            return;
        }
        $asset = $this->Asset_model->find_asset((int)($request['asset_id'] ?? 0));
        if (!$asset || !$this->ensure_asset_in_scope($asset, 'asset-management/changes')) {
            return;
        }

        $this->render('assets/change_detail', [
            'page_title' => 'Detail Perubahan Data Aset',
            'active_menu' => 'asset.master_change',
            'asset_nav_active' => 'changes',
            'request' => $request,
            'asset' => $asset,
            'can_edit' => $this->can(self::PAGE_MASTER_CHANGE, 'edit'),
            'can_delete' => $this->can(self::PAGE_MASTER_CHANGE, 'delete'),
            'can_cancel_own' => (int)($request['requested_by'] ?? 0) === $this->actor_user_id(),
        ]);
    }

    public function change_approve($id)
    {
        $this->master_change_action((int)$id, 'approve');
    }

    public function change_reject($id)
    {
        $this->master_change_action((int)$id, 'reject');
    }

    public function change_post($id)
    {
        $this->master_change_action((int)$id, 'post');
    }

    public function change_cancel($id)
    {
        $this->master_change_action((int)$id, 'cancel');
    }

    public function damage_index()
    {
        $this->require_permission(self::PAGE_DAMAGE, 'view');

        $filters = $this->damage_report_filters();
        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Asset_model->count_damage_reports($filters);
        $pg = $this->build_pagination($total, $perPage, $page);

        $this->render('assets/damage_index', [
            'page_title' => 'Laporan Kerusakan Aset',
            'active_menu' => 'asset.damage',
            'filters' => $filters,
            'pg' => $pg,
            'rows' => $this->Asset_model->list_damage_reports($filters, $pg['per_page'], $pg['offset']),
            'categories' => $this->Asset_model->category_options(false),
            'divisions' => $this->Asset_model->division_options(),
            'status_labels' => $this->Asset_model->status_labels(),
            'event_type_labels' => $this->Asset_model->damage_event_type_labels(),
            'can_create' => $this->can(self::PAGE_DAMAGE, 'create'),
            'can_edit' => $this->can(self::PAGE_DAMAGE, 'edit'),
            'can_delete' => $this->can(self::PAGE_DAMAGE, 'delete'),
        ]);
    }

    public function damage_create()
    {
        $this->require_permission(self::PAGE_DAMAGE, 'create');

        $this->render('assets/damage_form', [
            'page_title' => 'Tambah Laporan Kerusakan Aset',
            'active_menu' => 'asset.damage',
            'asset' => null,
        ]);
    }

    public function damage($id)
    {
        $this->require_permission(self::PAGE_DAMAGE, 'create');
        $asset = $this->Asset_model->find_asset((int)$id);
        if (!$asset) {
            show_404();
            return;
        }

        $this->render('assets/damage_form', [
            'page_title' => 'Lapor Aset Rusak',
            'active_menu' => 'asset.damage',
            'asset' => $asset,
        ]);
    }

    public function damage_asset_search()
    {
        $this->require_permission(self::PAGE_DAMAGE, 'create');

        $q = trim((string)$this->input->get('q', true));
        $rows = $q === '' ? [] : $this->Asset_model->search_assets_for_damage($q, 20, $this->active_division_id());
        $this->json_ok(['rows' => $rows]);
    }

    public function asset_search()
    {
        $this->require_permission(self::PAGE_ITEM, 'view');

        $q = trim((string)$this->input->get('q', true));
        $rows = $q === '' ? [] : $this->Asset_model->search_assets_for_damage($q, 20, $this->active_division_id());
        $this->json_ok(['rows' => $rows]);
    }

    public function damage_store($id = 0)
    {
        $this->require_permission(self::PAGE_DAMAGE, 'create');

        $assetId = (int)$id;
        if ($assetId <= 0) {
            $assetId = (int)$this->input->post('asset_id', true);
        }

        $asset = $this->Asset_model->find_asset($assetId);
        if (!$asset) {
            $this->session->set_flashdata('error', 'Pilih aset yang akan dilaporkan terlebih dahulu.');
            redirect('asset-management/damage/create');
            return;
        }

        $formUrl = 'asset-management/damage/' . $assetId;
        if (!$this->ensure_asset_storage_ready(['uploads/assets/evidence'])) {
            redirect($formUrl);
            return;
        }

        $reason = trim((string)$this->input->post('reason', true));
        if ($reason === '') {
            $this->session->set_flashdata('error', 'Alasan kerusakan/hilang wajib diisi.');
            redirect($formUrl);
            return;
        }
        if (empty($_FILES['evidence_file']['name'])) {
            $this->session->set_flashdata('error', 'Bukti foto wajib diupload saat melaporkan aset rusak/hilang.');
            redirect($formUrl);
            return;
        }

        $evidence = $this->handle_image_upload('evidence_file', 'uploads/assets/evidence', 'evidence');
        if ($evidence === false) {
            redirect($formUrl);
            return;
        }

        $payload = [
            'event_date' => $this->valid_date((string)$this->input->post('event_date', true)) ?: date('Y-m-d'),
            'to_status' => strtoupper((string)$this->input->post('to_status', true)),
            'condition_score_after' => (int)$this->input->post('condition_score_after', true),
            'estimated_loss_amount' => (float)$this->input->post('estimated_loss_amount', true),
            'reason' => $reason,
        ];

        $result = $this->Asset_model->record_damage($assetId, $payload, $evidence, $this->actor_user_id());
        if (!($result['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal menyimpan laporan.'));
            redirect($formUrl);
            return;
        }

        $this->session->set_flashdata('success', 'Laporan aset berhasil disimpan. Status aset langsung diperbarui dan audit trail tercatat.');
        redirect('asset-management/detail/' . $assetId);
    }

    public function damage_edit($eventId)
    {
        $this->require_permission(self::PAGE_DAMAGE, 'edit');

        $report = $this->Asset_model->find_damage_report((int)$eventId);
        if (!$report) {
            show_404();
            return;
        }
        if (empty($report['is_latest_event'])) {
            $this->session->set_flashdata('error', 'Laporan ini tidak bisa diedit karena aset sudah memiliki event lebih baru.');
            redirect('asset-management/damage');
            return;
        }

        $this->render('assets/damage_form', [
            'page_title' => 'Edit Laporan Kerusakan Aset',
            'active_menu' => 'asset.damage',
            'asset' => $report,
            'report' => $report,
            'is_edit' => true,
        ]);
    }

    public function damage_update($eventId)
    {
        $this->require_permission(self::PAGE_DAMAGE, 'edit');

        $eventId = (int)$eventId;
        $report = $this->Asset_model->find_damage_report($eventId);
        if (!$report) {
            show_404();
            return;
        }
        if (empty($report['is_latest_event'])) {
            $this->session->set_flashdata('error', 'Laporan ini tidak bisa diedit karena aset sudah memiliki event lebih baru.');
            redirect('asset-management/damage');
            return;
        }

        $reason = trim((string)$this->input->post('reason', true));
        if ($reason === '') {
            $this->session->set_flashdata('error', 'Alasan kerusakan/hilang wajib diisi.');
            redirect('asset-management/damage/edit/' . $eventId);
            return;
        }

        $evidence = $this->handle_image_upload('evidence_file', 'uploads/assets/evidence', 'evidence', false);
        if ($evidence === false) {
            redirect('asset-management/damage/edit/' . $eventId);
            return;
        }

        $payload = [
            'event_date' => $this->valid_date((string)$this->input->post('event_date', true)) ?: date('Y-m-d'),
            'to_status' => strtoupper((string)$this->input->post('to_status', true)),
            'condition_score_after' => (int)$this->input->post('condition_score_after', true),
            'estimated_loss_amount' => (float)$this->input->post('estimated_loss_amount', true),
            'reason' => $reason,
        ];

        $result = $this->Asset_model->update_damage_report($eventId, $payload, $evidence, $this->actor_user_id());
        if (!($result['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal mengupdate laporan.'));
            redirect('asset-management/damage/edit/' . $eventId);
            return;
        }

        $this->session->set_flashdata('success', 'Laporan kerusakan berhasil diupdate.');
        redirect('asset-management/damage');
    }

    public function damage_delete($eventId)
    {
        $this->require_permission(self::PAGE_DAMAGE, 'delete');

        $result = $this->Asset_model->delete_damage_report((int)$eventId, $this->actor_user_id());
        $this->session->set_flashdata(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? 'Laporan kerusakan dihapus dan status aset dikembalikan.' : (string)($result['message'] ?? 'Gagal menghapus laporan.'));
        redirect('asset-management/damage');
    }

    public function labels()
    {
        $this->require_permission(self::PAGE_LABEL, 'view');

        $filters = $this->asset_filters();
        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Asset_model->count_assets($filters);
        $pg = $this->build_pagination($total, $perPage, $page);

        $this->render('assets/labels', [
            'page_title' => 'QR Label Aset',
            'active_menu' => 'asset.label',
            'filters' => $filters,
            'pg' => $pg,
            'rows' => $this->Asset_model->list_assets($filters, $pg['per_page'], $pg['offset']),
            'categories' => $this->Asset_model->category_options(false),
            'divisions' => $this->Asset_model->division_options(),
            'status_labels' => $this->Asset_model->status_labels(),
        ]);
    }

    public function transfer()
    {
        $this->workflow_index('TRANSFER');
    }

    public function transfer_create()
    {
        $this->workflow_create('TRANSFER');
    }

    public function transfer_store()
    {
        $this->workflow_store('TRANSFER');
    }

    public function transfer_approve($id)
    {
        $this->workflow_action('TRANSFER', (int)$id, 'approve');
    }

    public function transfer_reject($id)
    {
        $this->workflow_action('TRANSFER', (int)$id, 'reject');
    }

    public function transfer_cancel($id)
    {
        $this->workflow_action('TRANSFER', (int)$id, 'cancel');
    }

    public function transfer_post($id)
    {
        $this->workflow_action('TRANSFER', (int)$id, 'post');
    }

    public function handover()
    {
        $this->workflow_index('HANDOVER');
    }

    public function handover_create()
    {
        $this->workflow_create('HANDOVER');
    }

    public function handover_store()
    {
        $this->workflow_store('HANDOVER');
    }

    public function handover_approve($id)
    {
        $this->workflow_action('HANDOVER', (int)$id, 'approve');
    }

    public function handover_reject($id)
    {
        $this->workflow_action('HANDOVER', (int)$id, 'reject');
    }

    public function handover_cancel($id)
    {
        $this->workflow_action('HANDOVER', (int)$id, 'cancel');
    }

    public function handover_post($id)
    {
        $this->workflow_action('HANDOVER', (int)$id, 'post');
    }

    public function maintenance()
    {
        $this->workflow_index('MAINTENANCE');
    }

    public function maintenance_create()
    {
        $this->workflow_create('MAINTENANCE');
    }

    public function maintenance_store()
    {
        $this->workflow_store('MAINTENANCE');
    }

    public function maintenance_approve($id)
    {
        $this->workflow_action('MAINTENANCE', (int)$id, 'approve');
    }

    public function maintenance_reject($id)
    {
        $this->workflow_action('MAINTENANCE', (int)$id, 'reject');
    }

    public function maintenance_cancel($id)
    {
        $this->workflow_action('MAINTENANCE', (int)$id, 'cancel');
    }

    public function maintenance_complete($id)
    {
        $this->workflow_action('MAINTENANCE', (int)$id, 'complete');
    }

    public function disposal()
    {
        $this->workflow_index('DISPOSAL');
    }

    public function disposal_create()
    {
        $this->workflow_create('DISPOSAL');
    }

    public function disposal_store()
    {
        $this->workflow_store('DISPOSAL');
    }

    public function disposal_approve($id)
    {
        $this->workflow_action('DISPOSAL', (int)$id, 'approve');
    }

    public function disposal_reject($id)
    {
        $this->workflow_action('DISPOSAL', (int)$id, 'reject');
    }

    public function disposal_cancel($id)
    {
        $this->workflow_action('DISPOSAL', (int)$id, 'cancel');
    }

    public function disposal_post($id)
    {
        $this->workflow_action('DISPOSAL', (int)$id, 'post');
    }

    public function recon()
    {
        $this->require_permission(self::PAGE_RECON, 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $divisionId = (int)$this->input->get('division_id', true);
        $scopeId = $this->active_division_id();
        if ($scopeId !== null) {
            $divisionId = $scopeId;
        }
        $filters = [
            'month' => $month,
            'status' => strtoupper(trim((string)$this->input->get('status', true) ?: 'ALL')),
            'division_id' => $divisionId,
        ];
        $activeTab = strtolower(trim((string)$this->input->get('tab', true) ?: 'preview'));
        if (!in_array($activeTab, ['preview', 'history'], true)) {
            $activeTab = 'preview';
        }
        $previewDivisionId = $divisionId > 0 ? $divisionId : null;
        $previewLimit = (int)$this->input->get('preview_rows', true);
        if (!in_array($previewLimit, [25, 50, 100, 250, 500], true)) {
            $previewLimit = 100;
        }
        $historyLimit = (int)$this->input->get('history_rows', true);
        if (!in_array($historyLimit, [10, 25, 50, 100], true)) {
            $historyLimit = 25;
        }

        $this->render('assets/recon_index', [
            'page_title' => 'Rekon Aset Bulanan',
            'active_menu' => 'asset.recon',
            'filters' => $filters,
            'active_tab' => $activeTab,
            'rows' => $this->Asset_model->list_recons($filters, $historyLimit),
            'movement' => $this->Asset_model->monthly_movement_summary($month, $previewDivisionId),
            'preview_rows' => $this->Asset_model->recon_preview_assets($month, $previewDivisionId, $previewLimit),
            'preview_summary' => $this->Asset_model->recon_preview_summary($month, $previewDivisionId),
            'preview_limit' => $previewLimit,
            'history_limit' => $historyLimit,
            'divisions' => $this->Asset_model->division_options(),
            'can_create' => $this->can(self::PAGE_RECON, 'create'),
            'can_edit' => $this->can(self::PAGE_RECON, 'edit'),
            'can_delete' => $this->can(self::PAGE_RECON, 'delete'),
        ]);
    }

    public function recon_generate()
    {
        $this->require_permission(self::PAGE_RECON, 'create');

        $month = trim((string)$this->input->post('period_month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $divisionId = (int)$this->input->post('division_id', true);
        $scopeId = $this->active_division_id();
        if ($scopeId !== null) {
            $divisionId = $scopeId;
        }
        $notes = trim((string)$this->input->post('notes', true));

        $result = $this->Asset_model->generate_recon($month, $divisionId > 0 ? $divisionId : null, $notes, $this->actor_user_id());
        if (!($result['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal generate rekon.'));
            redirect('asset-management/recon');
            return;
        }

        $this->session->set_flashdata('success', (string)($result['message'] ?? 'Rekon aset siap dicek.'));
        redirect('asset-management/recon/' . (int)$result['id']);
    }

    public function recon_detail($id)
    {
        $this->require_permission(self::PAGE_RECON, 'view');
        $recon = $this->Asset_model->find_recon((int)$id);
        if (!$recon) {
            show_404();
            return;
        }

        $this->render('assets/recon_detail', [
            'page_title' => 'Detail Rekon Aset',
            'active_menu' => 'asset.recon',
            'recon' => $recon,
            'lines' => $this->Asset_model->recon_lines((int)$id),
            'physical_status_labels' => $this->Asset_model->physical_status_labels(),
            'can_edit' => $this->can(self::PAGE_RECON, 'edit'),
            'can_post' => $this->can(self::PAGE_RECON, 'edit'),
            'can_cancel' => $this->can(self::PAGE_RECON, 'delete'),
        ]);
    }

    public function recon_save($id)
    {
        $this->require_permission(self::PAGE_RECON, 'edit');
        $id = (int)$id;
        $recon = $this->Asset_model->find_recon($id);
        if (!$recon) {
            show_404();
            return;
        }
        if (($recon['status'] ?? '') !== 'DRAFT') {
            $this->session->set_flashdata('error', 'Rekon yang sudah posting/cancel tidak bisa diedit.');
            redirect('asset-management/recon/' . $id);
            return;
        }

        $result = $this->persist_recon_lines_from_post($id);
        if (!($result['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal menyimpan checklist rekon.'));
            redirect('asset-management/recon/' . $id);
            return;
        }

        $this->session->set_flashdata('success', 'Checklist rekon disimpan.');
        redirect('asset-management/recon/' . $id);
    }

    public function recon_post($id)
    {
        $this->require_permission(self::PAGE_RECON, 'edit');
        $id = (int)$id;
        if ($this->input->method(true) === 'POST' && $this->input->post('physical_status') !== null) {
            $saveResult = $this->persist_recon_lines_from_post($id);
            if (!($saveResult['ok'] ?? false)) {
                $this->session->set_flashdata('error', (string)($saveResult['message'] ?? 'Gagal menyimpan checklist rekon sebelum posting.'));
                redirect('asset-management/recon/' . $id);
                return;
            }
        }

        $lines = $this->Asset_model->recon_lines($id);
        foreach ($lines as $line) {
            $status = strtoupper((string)($line['physical_status'] ?? 'NOT_CHECKED'));
            if (in_array($status, ['BROKEN', 'MISSING', 'NEED_REPAIR'], true)
                && (trim((string)($line['notes'] ?? '')) === '' || trim((string)($line['evidence_path'] ?? '')) === '')
            ) {
                $this->session->set_flashdata('error', 'Posting ditahan: semua aset rusak/hilang/butuh perbaikan wajib punya alasan dan bukti foto.');
                redirect('asset-management/recon/' . $id);
                return;
            }
        }

        $result = $this->Asset_model->post_recon($id, $this->actor_user_id());
        if (!($result['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal posting rekon.'));
            redirect('asset-management/recon/' . $id);
            return;
        }

        $this->session->set_flashdata('success', 'Rekon aset diposting. Status aset dan audit trail sudah diperbarui.');
        redirect('asset-management/recon/' . $id);
    }

    public function recon_cancel($id)
    {
        $this->require_permission(self::PAGE_RECON, 'delete');
        $id = (int)$id;
        $result = $this->Asset_model->cancel_recon($id, $this->actor_user_id());
        $this->session->set_flashdata(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? 'Rekon dibatalkan.' : (string)($result['message'] ?? 'Gagal membatalkan rekon.'));
        redirect('asset-management/recon');
    }

    public function depreciation()
    {
        $this->require_permission(self::PAGE_DEPRECIATION, 'view');

        $month = trim((string)$this->input->get('month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $divisionId = (int)$this->input->get('division_id', true);
        $scopeId = $this->active_division_id();
        if ($scopeId !== null) {
            $divisionId = $scopeId;
        }
        $status = strtoupper(trim((string)$this->input->get('status', true) ?: 'ALL'));
        if (!in_array($status, ['ALL', 'DRAFT', 'POSTED', 'CANCELLED'], true)) {
            $status = 'ALL';
        }
        $activeTab = strtolower(trim((string)$this->input->get('tab', true) ?: 'preview'));
        if (!in_array($activeTab, ['preview', 'history'], true)) {
            $activeTab = 'preview';
        }
        $previewLimit = (int)$this->input->get('preview_rows', true);
        if (!in_array($previewLimit, [25, 50, 100, 250, 500], true)) {
            $previewLimit = 100;
        }
        $historyLimit = (int)$this->input->get('history_rows', true);
        if (!in_array($historyLimit, [10, 25, 50, 100], true)) {
            $historyLimit = 25;
        }
        $previewDivisionId = $divisionId > 0 ? $divisionId : null;
        $filters = [
            'month' => $month,
            'division_id' => $divisionId,
            'status' => $status,
        ];

        $this->render('assets/depreciation', [
            'page_title' => 'Jurnal Penyusutan Aset',
            'active_menu' => 'asset.depreciation',
            'filters' => $filters,
            'active_tab' => $activeTab,
            'preview_rows' => $this->Asset_model->depreciation_preview($month, $previewDivisionId, $previewLimit),
            'preview_summary' => $this->Asset_model->depreciation_summary($month, $previewDivisionId),
            'runs' => $this->Asset_model->list_depreciation_runs($filters, $historyLimit),
            'preview_limit' => $previewLimit,
            'history_limit' => $historyLimit,
            'divisions' => $this->Asset_model->division_options(),
            'extension_ready' => $this->Asset_model->extension_ready(),
            'can_create' => $this->can(self::PAGE_DEPRECIATION, 'create'),
            'can_edit' => $this->can(self::PAGE_DEPRECIATION, 'edit'),
            'can_delete' => $this->can(self::PAGE_DEPRECIATION, 'delete'),
        ]);
    }

    public function depreciation_generate()
    {
        $this->require_permission(self::PAGE_DEPRECIATION, 'create');

        $month = trim((string)$this->input->post('period_month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        $divisionId = (int)$this->input->post('division_id', true);
        $scopeId = $this->active_division_id();
        if ($scopeId !== null) {
            $divisionId = $scopeId;
        }

        $result = $this->Asset_model->create_depreciation_run(
            $month,
            $divisionId > 0 ? $divisionId : null,
            trim((string)$this->input->post('notes', true)),
            $this->actor_user_id()
        );

        $this->session->set_flashdata(($result['ok'] ?? false) ? 'success' : 'error', (string)($result['message'] ?? (($result['ok'] ?? false) ? 'Staging penyusutan dibuat.' : 'Gagal membuat staging penyusutan.')));
        redirect('asset-management/depreciation?tab=history&month=' . rawurlencode($month) . '&division_id=' . (int)$divisionId);
    }

    public function depreciation_post($id)
    {
        $this->require_permission(self::PAGE_DEPRECIATION, 'edit');

        $result = $this->Asset_model->post_depreciation_run((int)$id, $this->actor_user_id());
        $this->session->set_flashdata(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? 'Staging jurnal penyusutan diposting.' : (string)($result['message'] ?? 'Gagal posting penyusutan.'));
        redirect('asset-management/depreciation?tab=history');
    }

    public function depreciation_cancel($id)
    {
        $this->require_permission(self::PAGE_DEPRECIATION, 'delete');

        $result = $this->Asset_model->cancel_depreciation_run((int)$id);
        $this->session->set_flashdata(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? 'Staging jurnal penyusutan dibatalkan.' : (string)($result['message'] ?? 'Gagal membatalkan penyusutan.'));
        redirect('asset-management/depreciation?tab=history');
    }

    private function workflow_index(string $type): void
    {
        $type = strtoupper($type);
        $config = $this->workflow_config($type);
        if (!$config) {
            show_404();
            return;
        }

        $this->require_permission((string)$config['page_code'], 'view');

        $filters = $this->workflow_filters();
        $perPage = $this->per_page();
        $page = $this->page();
        $total = $this->Asset_model->count_workflows($type, $filters);
        $pg = $this->build_pagination($total, $perPage, $page);

        $this->render('assets/workflow_index', [
            'page_title' => (string)$config['label'],
            'active_menu' => 'asset.' . $this->workflow_slug($type),
            'asset_nav_active' => $this->workflow_slug($type),
            'type' => $type,
            'config' => $config,
            'filters' => $filters,
            'pg' => $pg,
            'rows' => $this->Asset_model->list_workflows($type, $filters, $pg['per_page'], $pg['offset']),
            'divisions' => $this->Asset_model->division_options(),
            'status_labels' => $this->Asset_model->workflow_status_labels(),
            'priority_labels' => $this->Asset_model->priority_labels(),
            'extension_ready' => $this->Asset_model->extension_ready(),
            'can_create' => $this->can((string)$config['page_code'], 'create'),
            'can_edit' => $this->can((string)$config['page_code'], 'edit'),
            'can_delete' => $this->can((string)$config['page_code'], 'delete'),
        ]);
    }

    private function workflow_create(string $type): void
    {
        $type = strtoupper($type);
        $config = $this->workflow_config($type);
        if (!$config) {
            show_404();
            return;
        }
        $this->require_permission((string)$config['page_code'], 'create');

        $asset = null;
        $assetId = (int)$this->input->get('asset_id', true);
        if ($assetId > 0) {
            $asset = $this->Asset_model->find_asset($assetId);
        }

        $this->render('assets/workflow_form', [
            'page_title' => 'Tambah ' . (string)$config['short_label'],
            'active_menu' => 'asset.' . $this->workflow_slug($type),
            'asset_nav_active' => $this->workflow_slug($type),
            'type' => $type,
            'config' => $config,
            'asset' => $asset,
            'divisions' => $this->Asset_model->division_options(),
            'outlets' => $this->Asset_model->outlet_options(),
            'employees' => $this->Asset_model->employee_options(),
            'priority_labels' => $this->Asset_model->priority_labels(),
        ]);
    }

    private function workflow_store(string $type): void
    {
        $type = strtoupper($type);
        $config = $this->workflow_config($type);
        if (!$config) {
            show_404();
            return;
        }
        $this->require_permission((string)$config['page_code'], 'create');

        $url = (string)$config['url'];
        $createUrl = $url . '/create';
        if (!$this->Asset_model->extension_ready()) {
            $this->session->set_flashdata('error', 'Tabel workflow aset belum siap. Jalankan SQL ekstensi aset terlebih dahulu.');
            redirect($createUrl);
            return;
        }

        $payload = $this->workflow_payload_from_post($type);
        $asset = $this->Asset_model->find_asset((int)$payload['asset_id']);
        if (!$asset) {
            $this->session->set_flashdata('error', 'Pilih aset terlebih dahulu dari pencarian.');
            redirect($createUrl);
            return;
        }

        $scopeId = $this->active_division_id();
        if ($scopeId !== null && (int)($asset['division_id'] ?? 0) !== $scopeId) {
            $this->session->set_flashdata('error', 'Aset ini berada di luar scope divisi Anda.');
            redirect($createUrl);
            return;
        }

        $reason = trim((string)($payload['reason'] ?? ''));
        if ($reason === '') {
            $this->session->set_flashdata('error', 'Alasan/catatan wajib diisi.');
            redirect($createUrl . '?asset_id=' . (int)$asset['id']);
            return;
        }

        if ($type === 'TRANSFER') {
            $hasTarget = (int)($payload['to_division_id'] ?? 0) > 0
                || (int)($payload['to_outlet_id'] ?? 0) > 0
                || (int)($payload['to_employee_id'] ?? 0) > 0
                || trim((string)($payload['to_location'] ?? '')) !== '';
            if (!$hasTarget) {
                $this->session->set_flashdata('error', 'Isi minimal satu tujuan mutasi: divisi, outlet, lokasi, atau PIC tujuan.');
                redirect($createUrl . '?asset_id=' . (int)$asset['id']);
                return;
            }
        }

        if ($type === 'HANDOVER' && (int)($payload['to_employee_id'] ?? 0) <= 0) {
            $this->session->set_flashdata('error', 'PIC penerima wajib dipilih untuk serah terima aset.');
            redirect($createUrl . '?asset_id=' . (int)$asset['id']);
            return;
        }

        if ($type === 'DISPOSAL' && empty($_FILES['evidence_file']['name'])) {
            $this->session->set_flashdata('error', 'Disposal wajib menyertakan bukti foto/dokumen visual aset.');
            redirect($createUrl . '?asset_id=' . (int)$asset['id']);
            return;
        }

        $evidence = $this->handle_image_upload('evidence_file', 'uploads/assets/evidence', 'evidence', false);
        if ($evidence === false) {
            redirect($createUrl . '?asset_id=' . (int)$asset['id']);
            return;
        }

        $result = $this->Asset_model->create_workflow($type, $payload, $evidence, $this->actor_user_id());
        if (!($result['ok'] ?? false)) {
            $this->session->set_flashdata('error', (string)($result['message'] ?? 'Gagal menyimpan workflow aset.'));
            redirect($createUrl . '?asset_id=' . (int)$asset['id']);
            return;
        }

        $this->session->set_flashdata('success', (string)$config['short_label'] . ' tersimpan sebagai ' . (string)($result['workflow_no'] ?? 'workflow') . '. Lanjutkan approve/posting saat sudah dicek.');
        redirect($url);
    }

    private function workflow_action(string $type, int $id, string $action): void
    {
        $type = strtoupper($type);
        $action = strtolower($action);
        $config = $this->workflow_config($type);
        if (!$config) {
            show_404();
            return;
        }

        $permission = $action === 'cancel' ? 'delete' : 'edit';
        $this->require_permission((string)$config['page_code'], $permission);

        switch ($action) {
            case 'approve':
                $result = $this->Asset_model->approve_workflow($type, $id, $this->actor_user_id());
                $successMessage = (string)$config['short_label'] . ' disetujui.';
                break;
            case 'reject':
                $result = $this->Asset_model->reject_workflow($type, $id, $this->actor_user_id());
                $successMessage = (string)$config['short_label'] . ' ditolak.';
                break;
            case 'cancel':
                $result = $this->Asset_model->cancel_workflow($type, $id, $this->actor_user_id());
                $successMessage = (string)$config['short_label'] . ' dibatalkan.';
                break;
            case 'post':
                $result = $this->Asset_model->post_workflow($type, $id, $this->actor_user_id());
                $successMessage = (string)$config['short_label'] . ' diposting dan data aset diperbarui.';
                break;
            case 'complete':
                if ($type !== 'MAINTENANCE') {
                    show_404();
                    return;
                }
                $result = $this->Asset_model->complete_maintenance($id, (float)$this->input->post('actual_cost', true), $this->actor_user_id());
                $successMessage = 'Maintenance selesai dan audit trail aset tercatat.';
                break;
            default:
                show_404();
                return;
        }

        $this->session->set_flashdata(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? $successMessage : (string)($result['message'] ?? 'Aksi workflow gagal.'));
        redirect((string)$config['url']);
    }

    private function workflow_config(string $type): ?array
    {
        $type = strtoupper($type);
        $configs = $this->Asset_model->workflow_configs();
        return $configs[$type] ?? null;
    }

    private function workflow_slug(string $type): string
    {
        $type = strtoupper($type);
        return [
            'TRANSFER' => 'transfer',
            'HANDOVER' => 'handover',
            'MAINTENANCE' => 'maintenance',
            'DISPOSAL' => 'disposal',
        ][$type] ?? strtolower($type);
    }

    private function workflow_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true) ?: 'ALL'));
        if ($status !== 'ALL' && !isset($this->Asset_model->workflow_status_labels()[$status])) {
            $status = 'ALL';
        }

        $divisionId = (int)$this->input->get('division_id', true);
        $scopeId = $this->active_division_id();
        if ($scopeId !== null) {
            $divisionId = $scopeId;
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'date_from' => $this->valid_date((string)$this->input->get('date_from', true)),
            'date_to' => $this->valid_date((string)$this->input->get('date_to', true)),
            'division_id' => $divisionId,
        ];
    }

    private function workflow_payload_from_post(string $type): array
    {
        $type = strtoupper($type);
        $workflowDate = $this->valid_date((string)$this->input->post('workflow_date', true)) ?: date('Y-m-d');
        $maintenanceType = trim((string)$this->input->post('maintenance_type', true));
        if ($type === 'MAINTENANCE' && $maintenanceType === '') {
            $maintenanceType = 'Preventive';
        }

        $priority = strtoupper(trim((string)$this->input->post('priority', true) ?: 'NORMAL'));
        if (!isset($this->Asset_model->priority_labels()[$priority])) {
            $priority = 'NORMAL';
        }

        return [
            'asset_id' => (int)$this->input->post('asset_id', true),
            'workflow_date' => $workflowDate,
            'due_date' => $this->valid_date((string)$this->input->post('due_date', true)),
            'to_division_id' => (int)$this->input->post('to_division_id', true),
            'to_outlet_id' => (int)$this->input->post('to_outlet_id', true),
            'to_location' => trim((string)$this->input->post('to_location', true)),
            'to_employee_id' => (int)$this->input->post('to_employee_id', true),
            'maintenance_type' => $maintenanceType,
            'priority' => $priority,
            'vendor_name' => trim((string)$this->input->post('vendor_name', true)),
            'disposal_type' => strtoupper(trim((string)$this->input->post('disposal_type', true) ?: 'DISPOSED')),
            'estimated_cost' => max(0, (float)$this->input->post('estimated_cost', true)),
            'actual_cost' => max(0, (float)$this->input->post('actual_cost', true)),
            'disposal_value' => max(0, (float)$this->input->post('disposal_value', true)),
            'reason' => trim((string)$this->input->post('reason', true)),
        ];
    }

    private function persist_recon_lines_from_post(int $id): array
    {
        if (!$this->ensure_asset_storage_ready(['uploads/assets/evidence'])) {
            return ['ok' => false, 'message' => 'Folder upload bukti rekon belum siap.'];
        }

        $existingLines = [];
        foreach ($this->Asset_model->recon_lines($id) as $line) {
            $existingLines[(int)$line['id']] = $line;
        }

        $statusRows = (array)$this->input->post('physical_status', true);
        $scoreRows = (array)$this->input->post('condition_score', true);
        $noteRows = (array)$this->input->post('notes', true);
        $lines = [];

        foreach ($statusRows as $lineId => $status) {
            $lineId = (int)$lineId;
            if ($lineId <= 0 || !isset($existingLines[$lineId])) {
                continue;
            }
            $status = strtoupper((string)$status);
            $notes = trim((string)($noteRows[$lineId] ?? ''));
            $issue = in_array($status, ['BROKEN', 'MISSING', 'NEED_REPAIR'], true);

            $upload = $this->handle_image_upload('evidence_' . $lineId, 'uploads/assets/evidence', 'evidence', false);
            if ($upload === false) {
                return ['ok' => false, 'message' => 'Gagal upload bukti rekon. Pastikan file berupa JPG, PNG, atau WEBP dan ukuran tidak lebih dari 8 MB.'];
            }
            if ($issue && $notes === '') {
                return ['ok' => false, 'message' => 'Baris aset bermasalah wajib diberi catatan/alasan.'];
            }
            if ($issue && empty($upload['evidence_path']) && empty($existingLines[$lineId]['evidence_path'])) {
                return ['ok' => false, 'message' => 'Baris aset rusak/hilang/butuh perbaikan wajib menyertakan bukti foto.'];
            }

            $lines[$lineId] = [
                'physical_status' => $status,
                'condition_score' => $scoreRows[$lineId] ?? null,
                'notes' => $notes,
            ];
            if (!empty($upload['evidence_path'])) {
                $lines[$lineId]['evidence_path'] = $upload['evidence_path'];
                $lines[$lineId]['evidence_mime'] = $upload['evidence_mime'] ?? null;
            }
        }

        return $this->Asset_model->save_recon_lines($id, $lines, $this->actor_user_id());
    }

    private function master_change_action(int $id, string $action): void
    {
        $request = $this->Asset_model->find_master_change_request($id);
        if (!$request) {
            show_404();
            return;
        }
        $asset = $this->Asset_model->find_asset((int)($request['asset_id'] ?? 0));
        if (!$asset || !$this->ensure_asset_in_scope($asset, 'asset-management/changes')) {
            return;
        }

        $action = strtolower($action);
        if ($action === 'cancel') {
            $canCancelOwn = (int)($request['requested_by'] ?? 0) === $this->actor_user_id();
            if (!$canCancelOwn && !$this->can(self::PAGE_MASTER_CHANGE, 'delete')) {
                $this->session->set_flashdata('error', 'Hanya pembuat pengajuan atau petugas berwenang yang dapat membatalkan pengajuan ini.');
                redirect('asset-management/changes/' . $id);
                return;
            }
            $result = $this->Asset_model->cancel_master_change_request($id, $this->actor_user_id());
            $successMessage = 'Pengajuan perubahan dibatalkan.';
        } else {
            $this->require_permission(self::PAGE_MASTER_CHANGE, 'edit');
            if ($action === 'approve') {
                $result = $this->Asset_model->approve_master_change_request($id, $this->actor_user_id());
                $successMessage = 'Pengajuan perubahan disetujui. Terapkan saat siap memperbarui data aset.';
            } elseif ($action === 'reject') {
                $result = $this->Asset_model->reject_master_change_request(
                    $id,
                    trim((string)$this->input->post('rejection_reason', true)),
                    $this->actor_user_id()
                );
                $successMessage = 'Pengajuan perubahan ditolak.';
            } elseif ($action === 'post') {
                $result = $this->Asset_model->post_master_change_request($id, $this->actor_user_id());
                $successMessage = 'Pengajuan perubahan diterapkan ke data aset dan tercatat di riwayat.';
            } else {
                show_404();
                return;
            }
        }

        $this->session->set_flashdata(!empty($result['ok']) ? 'success' : 'error', !empty($result['ok']) ? $successMessage : (string)($result['message'] ?? 'Aksi pengajuan perubahan gagal.'));
        redirect('asset-management/changes/' . $id);
    }

    private function render_master_change_form(array $asset): void
    {
        $this->render('assets/change_form', [
            'page_title' => 'Ajukan Perubahan Data Aset',
            'active_menu' => 'asset.master_change',
            'asset_nav_active' => 'changes',
            'asset' => $asset,
            'categories' => $this->Asset_model->category_options(false),
        ]);
    }

    private function master_change_payload_from_post(array $asset): array
    {
        $assetName = trim((string)$this->input->post('asset_name', true));
        if ($assetName === '') {
            return ['ok' => false, 'message' => 'Nama aset wajib diisi.'];
        }

        $categoryId = (int)$this->input->post('category_id', true);
        $category = $this->category_by_id($categoryId);
        $purchaseDate = $this->valid_date((string)$this->input->post('purchase_date', true));
        $acquisitionDate = $this->valid_date((string)$this->input->post('acquisition_date', true)) ?: $purchaseDate;
        $method = strtoupper(trim((string)$this->input->post('depreciation_method', true)));
        if (!in_array($method, ['NONE', 'STRAIGHT_LINE'], true)) {
            $method = strtoupper((string)($asset['depreciation_method'] ?? $category['default_depreciation_method'] ?? 'STRAIGHT_LINE'));
        }
        $life = (int)$this->input->post('useful_life_months', true);
        if ($life <= 0 && $method !== 'NONE') {
            $life = (int)($category['default_useful_life_months'] ?? $asset['useful_life_months'] ?? 36);
        }
        $startMonth = trim((string)$this->input->post('depreciation_start_month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $startMonth)) {
            $startMonth = $acquisitionDate ? date('Y-m', strtotime($acquisitionDate)) : null;
        }

        $reason = trim((string)$this->input->post('reason', true));
        if ($reason === '') {
            return ['ok' => false, 'message' => 'Alasan perubahan wajib diisi.'];
        }

        return [
            'ok' => true,
            'reason' => $reason,
            'data' => [
                'asset_name' => $assetName,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'brand' => $this->null_if_empty((string)$this->input->post('brand', true)),
                'model_name' => $this->null_if_empty((string)$this->input->post('model_name', true)),
                'serial_no' => $this->null_if_empty((string)$this->input->post('serial_no', true)),
                'batch_no' => $this->null_if_empty((string)$this->input->post('batch_no', true)),
                'purchase_date' => $purchaseDate,
                'acquisition_date' => $acquisitionDate,
                'acquisition_cost' => max(0, (float)$this->input->post('acquisition_cost', true)),
                'residual_value' => max(0, (float)$this->input->post('residual_value', true)),
                'useful_life_months' => max(0, min(600, $life)),
                'depreciation_method' => $method,
                'depreciation_start_month' => $startMonth,
                'notes' => $this->null_if_empty((string)$this->input->post('notes', true)),
            ],
        ];
    }

    private function master_change_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true) ?: 'ALL'));
        if ($status !== 'ALL' && !isset($this->Asset_model->master_change_status_labels()[$status])) {
            $status = 'ALL';
        }
        $divisionId = (int)$this->input->get('division_id', true);
        $scopeId = $this->active_division_id();
        if ($scopeId !== null) {
            $divisionId = $scopeId;
        }
        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'date_from' => $this->valid_date((string)$this->input->get('date_from', true)),
            'date_to' => $this->valid_date((string)$this->input->get('date_to', true)),
            'division_id' => $divisionId,
            'division_scope_id' => $scopeId ?? 0,
        ];
    }

    private function ensure_asset_in_scope(array $asset, string $fallback): bool
    {
        $scopeId = $this->active_division_id();
        if ($scopeId === null || (int)($asset['division_id'] ?? 0) === $scopeId) {
            return true;
        }
        $this->session->set_flashdata('error', 'Aset ini berada di luar scope divisi Anda.');
        redirect($fallback);
        return false;
    }

    private function asset_group_url(string $groupKey, ?int $divisionId = null): string
    {
        $url = 'asset-management/group/' . rawurlencode($groupKey);
        if ($divisionId !== null && $divisionId > 0) {
            $url .= '?division_id=' . $divisionId;
        }
        return $url;
    }

    private function render_form(?array $asset): void
    {
        $this->render('assets/form', [
            'page_title' => $asset ? 'Edit Aset' : 'Tambah Aset',
            'active_menu' => 'asset.item',
            'asset' => $asset,
            'categories' => $this->Asset_model->category_options(false),
            'divisions' => $this->Asset_model->division_options(),
            'outlets' => $this->Asset_model->outlet_options(),
            'employees' => $this->Asset_model->employee_options(),
            'status_labels' => $this->Asset_model->status_labels(),
            'is_edit' => $asset !== null,
        ]);
    }

    private function asset_payload_from_post(bool $isEdit = false): array
    {
        $assetName = trim((string)$this->input->post('asset_name', true));
        if ($assetName === '') {
            return ['ok' => false, 'message' => 'Nama aset wajib diisi.'];
        }

        $categoryId = (int)$this->input->post('category_id', true);
        $category = $this->category_by_id($categoryId);
        $purchaseDate = $this->valid_date((string)$this->input->post('purchase_date', true));
        $acquisitionDate = $this->valid_date((string)$this->input->post('acquisition_date', true)) ?: ($purchaseDate ?: date('Y-m-d'));
        $method = strtoupper((string)$this->input->post('depreciation_method', true));
        if (!in_array($method, ['NONE', 'STRAIGHT_LINE'], true)) {
            $method = (string)($category['default_depreciation_method'] ?? 'STRAIGHT_LINE');
        }
        $life = (int)$this->input->post('useful_life_months', true);
        if ($life <= 0) {
            $life = (int)($category['default_useful_life_months'] ?? 36);
        }
        $status = strtoupper((string)$this->input->post('status', true));
        if (!isset($this->Asset_model->status_labels()[$status])) {
            $status = 'ACTIVE';
        }
        $startMonth = trim((string)$this->input->post('depreciation_start_month', true));
        if (!preg_match('/^\d{4}-\d{2}$/', $startMonth)) {
            $startMonth = date('Y-m', strtotime($acquisitionDate) ?: time());
        }

        $data = [
            'asset_name' => $assetName,
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'brand' => $this->null_if_empty((string)$this->input->post('brand', true)),
            'model_name' => $this->null_if_empty((string)$this->input->post('model_name', true)),
            'serial_no' => $this->null_if_empty((string)$this->input->post('serial_no', true)),
            'batch_no' => $this->null_if_empty((string)$this->input->post('batch_no', true)) ?: 'ASB-' . date('Ymd-His'),
            'purchase_date' => $purchaseDate,
            'acquisition_date' => $acquisitionDate,
            'acquisition_cost' => max(0, (float)$this->input->post('acquisition_cost', true)),
            'residual_value' => max(0, (float)$this->input->post('residual_value', true)),
            'useful_life_months' => max(0, min(600, $life)),
            'depreciation_method' => $method,
            'depreciation_start_month' => $startMonth,
            'division_id' => $this->scoped_division_id((int)$this->input->post('division_id', true)),
            'outlet_id' => (int)$this->input->post('outlet_id', true) > 0 ? (int)$this->input->post('outlet_id', true) : null,
            'current_location' => $this->null_if_empty((string)$this->input->post('current_location', true)),
            'custodian_employee_id' => (int)$this->input->post('custodian_employee_id', true) > 0 ? (int)$this->input->post('custodian_employee_id', true) : null,
            'status' => $status,
            'condition_score' => max(0, min(100, (int)$this->input->post('condition_score', true))),
            'notes' => $this->null_if_empty((string)$this->input->post('notes', true)),
        ];

        if (!$isEdit && $data['condition_score'] <= 0 && $status === 'ACTIVE') {
            $data['condition_score'] = 100;
        }

        return ['ok' => true, 'data' => $data];
    }

    private function asset_filters(): array
    {
        $status = strtoupper(trim((string)$this->input->get('status', true) ?: 'ALL'));
        $divisionId = (int)$this->input->get('division_id', true);
        $scopeId = $this->active_division_id();
        if ($scopeId !== null) {
            $divisionId = $scopeId;
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'status' => $status,
            'category_id' => (int)$this->input->get('category_id', true),
            'division_id' => $divisionId,
            'division_scope_id' => $scopeId ?? 0,
        ];
    }

    private function damage_report_filters(): array
    {
        $divisionId = (int)$this->input->get('division_id', true);
        $scopeId = $this->active_division_id();
        if ($scopeId !== null) {
            $divisionId = $scopeId;
        }

        $dateFrom = $this->valid_date((string)$this->input->get('date_from', true));
        $dateTo = $this->valid_date((string)$this->input->get('date_to', true));
        if ($dateFrom === null && $dateTo === null) {
            $dateFrom = date('Y-m-01');
            $dateTo = date('Y-m-d');
        }

        $eventType = strtoupper(trim((string)$this->input->get('event_type', true) ?: 'ALL'));
        if ($eventType !== 'ALL' && !isset($this->Asset_model->damage_event_type_labels()[$eventType])) {
            $eventType = 'ALL';
        }

        return [
            'q' => trim((string)$this->input->get('q', true)),
            'event_type' => $eventType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'category_id' => (int)$this->input->get('category_id', true),
            'division_id' => $divisionId,
            'division_scope_id' => $scopeId ?? 0,
        ];
    }

    private function category_by_id(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        foreach ($this->Asset_model->category_options(false) as $row) {
            if ((int)$row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    private function scoped_division_id(int $requested): ?int
    {
        $scopeId = $this->active_division_id();
        if ($scopeId !== null) {
            return $scopeId;
        }
        return $requested > 0 ? $requested : null;
    }

    private function serial_numbers_from_post(): array
    {
        $raw = (string)$this->input->post('serial_numbers', true);
        $rows = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $row = trim((string)$row);
            if ($row !== '') {
                $out[] = $row;
            }
        }
        return $out;
    }

    private function handle_image_upload(string $fieldName, string $relativeDir, string $keyPrefix, bool $setEmptyError = true)
    {
        if (empty($_FILES[$fieldName]['name'])) {
            return [];
        }

        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        if (!$this->ensure_asset_storage_ready([$relativeDir])) {
            return false;
        }

        $config = [
            'upload_path' => FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir),
            'allowed_types' => 'jpg|jpeg|png|webp',
            'max_size' => 8192,
            'encrypt_name' => true,
            'remove_spaces' => true,
        ];

        $this->load->library('upload', $config);
        $this->upload->initialize($config, true);
        if (!$this->upload->do_upload($fieldName)) {
            if ($setEmptyError) {
                $this->session->set_flashdata('error', strip_tags((string)$this->upload->display_errors('', '')));
            } else {
                $this->session->set_flashdata('error', strip_tags((string)$this->upload->display_errors('', '')));
            }
            return false;
        }

        $up = $this->upload->data();
        return [
            $keyPrefix . '_path' => $relativeDir . '/' . $up['file_name'],
            $keyPrefix . '_mime' => (string)($up['file_type'] ?? ''),
        ];
    }

    private function ensure_asset_storage_ready(array $relativeDirs): bool
    {
        foreach ($relativeDirs as $relativeDir) {
            $relativeDir = trim(str_replace('\\', '/', (string)$relativeDir), '/');
            if ($relativeDir === '') {
                continue;
            }
            $absDir = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
            if (!is_dir($absDir) && !@mkdir($absDir, 0770, true) && !is_dir($absDir)) {
                $this->session->set_flashdata('error', 'Folder upload aset tidak bisa dibuat: ' . $absDir);
                return false;
            }
            if (!is_writable($absDir)) {
                $this->session->set_flashdata('error', 'Folder upload aset tidak writable: ' . $absDir);
                return false;
            }
        }
        return true;
    }

    private function per_page(): int
    {
        $pp = (int)$this->input->get('per_page', true);
        return in_array($pp, [10, 25, 50, 100], true) ? $pp : 25;
    }

    private function page(): int
    {
        return max(1, (int)$this->input->get('page', true));
    }

    private function build_pagination(int $total, int $perPage, int $page): array
    {
        $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
        $page = min(max(1, $page), $totalPages);
        return [
            'total' => $total,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $totalPages,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    private function json_ok(array $data = [], int $status = 200): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        $payload = array_merge(['ok' => true], $data);
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function actor_user_id(): int
    {
        return (int)($this->current_user['id'] ?? 0);
    }

    private function valid_date(string $value): ?string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function null_if_empty(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}

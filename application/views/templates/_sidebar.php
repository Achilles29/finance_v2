        <!-- Menu -->

        <?php
        $role_id = $this->session->userdata('role_id');
        $display_name = $this->session->userdata('nama_lengkap')
            ?: $this->session->userdata('nama')
            ?: $this->session->userdata('username')
            ?: 'Pengguna';
        $display_initial = strtoupper(substr(trim($display_name), 0, 1));
        $role_label = 'Portal Internal';

        // Tentukan dashboard sesuai role
        if ($role_id == 1) {
            $dashboard_url = base_url('superadmin/dashboard');
            $role_label = 'Super Admin';
        } elseif ($role_id == 2) {
            $dashboard_url = base_url('admin_layanan/dashboard');
            $role_label = 'Admin Layanan';
        } elseif ($role_id == 3) {
            $dashboard_url = base_url('dashboard');
            $role_label = 'Modul Internal';
        } elseif ($role_id == 4) {
            $dashboard_url = base_url('masyarakat/dashboard');
            $role_label = 'Masyarakat';
        } else {
            $dashboard_url = base_url('dashboard'); // fallback jika tidak ada role
        }
        ?>

        <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
            <div class="app-brand demo">
                <a href="<?= $dashboard_url; ?>" class="app-brand-link">
                    <span class="app-brand-logo demo me-1">
                        <img src="<?= (is_file(FCPATH . 'assets/img/logo.png') ? base_url('assets/img/logo.png') : base_url('assets/img/business-placeholder.svg')) ?>"
                            alt="Logo Pemkab Rembang"
                            style="height:60px; width:auto; border-radius:4px; object-fit:contain;" />
                    </span>
                    <span class="app-brand-text demo menu-text fw-semibold ms-2">MPP</span>
                </a>

                <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto">
                    <i class="menu-toggle-icon d-xl-inline-block align-middle"></i>
                </a>
            </div>

            <div class="menu-inner-shadow"></div>

            <ul class="menu-inner py-1">

                <!-- Dashboard -->
                <li class="menu-item">
                    <a href="<?= $dashboard_url; ?>" class="menu-link">
                        <i class="menu-icon ri ri-home-3-line"></i>
                        <div>Dashboard</div>
                    </a>
                </li>

                <?php if ($role_id == 1): // SUPERADMIN 
                ?>
<li class="menu-item has-sub">
    <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon ri ri-dashboard-3-line"></i>
        <div>Operasional Antrian</div>
    </a>
    <ul class="menu-sub">

        <li class="menu-item">
            <a href="<?= base_url('antrian_display'); ?>" class="menu-link">
                <i class="menu-icon ri ri-tv-2-line"></i>
                <div>Monitor Display</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= base_url('pendaftaran/manual'); ?>" class="menu-link">
                <i class="menu-icon ri ri-user-follow-line"></i>
                <div>Front Desk</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= base_url('pendaftaran/manual_v2'); ?>" class="menu-link">
                <i class="menu-icon ri ri-group-line"></i>
                <div>Front Desk V2</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= base_url('pendaftaran/manual_v2_tab'); ?>" class="menu-link">
                <i class="menu-icon ri ri-layout-top-2-line"></i>
                <div>Front Desk Tab</div>
            </a>
        </li>

    </ul>
</li>


<li class="menu-item has-sub">
    <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon ri ri-settings-3-line"></i>
        <div>Manajemen Sistem</div>
    </a>
    <ul class="menu-sub">

        <li class="menu-item">
            <a href="<?= base_url('superadmin/users'); ?>" class="menu-link">
                <i class="menu-icon ri ri-user-star-line"></i>
                <div>Manajemen User</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= site_url('superadmin/verifikasi_user') ?>" class="menu-link">
                <i class="menu-icon ri ri-shield-check-line"></i>
                <div>Verifikasi Akun</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= site_url('superadmin/pengaturan_email') ?>" class="menu-link">
                <i class="menu-icon ri ri-mail-settings-line"></i>
                <div>Pengaturan Email</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= base_url('superadmin/instansi'); ?>" class="menu-link">
                <i class="menu-icon ri ri-government-line"></i>
                <div>Instansi</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= base_url('jenislayanan'); ?>" class="menu-link">
                <i class="menu-icon ri ri-briefcase-2-line"></i>
                <div>Jenis Layanan</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= base_url('readme/printer'); ?>" class="menu-link">
                <i class="menu-icon ri ri-printer-line"></i>
                <div>Panduan Printer</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= base_url('superadmin/kelola_layanan'); ?>" class="menu-link">
                <i class="menu-icon ri ri-toggle-line"></i>
                <div>Kelola Layanan</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= base_url('superadmin/sektor_display'); ?>" class="menu-link">
                <i class="menu-icon ri ri-layout-grid-line"></i>
                <div>Pengaturan Sektor</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= base_url('superadmin/video_setting'); ?>" class="menu-link">
                <i class="menu-icon ri ri-slideshow-line"></i>
                <div>Pengaturan Video</div>
            </a>
        </li>

        <li class="menu-item">
            <a href="<?= base_url('superadmin/audio_speed_setting'); ?>" class="menu-link">
                <i class="menu-icon ri ri-volume-up-line"></i>
                <div>Pengaturan Speed Suara</div>
            </a>
        </li>

    </ul>
</li>


<li class="menu-item <?= strpos(current_url(), 'laporan') !== false ? 'open active' : '' ?>">
    <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon ri ri-bar-chart-grouped-line"></i>
        <div>Laporan</div>
    </a>

    <ul class="menu-sub">
        <li class="menu-item <?= strpos(current_url(), 'laporan/dashboard') !== false ? 'active' : '' ?>">
            <a href="<?= base_url('laporan/dashboard'); ?>" class="menu-link">
                <i class="menu-icon ri ri-dashboard-line"></i>
                <div>Dashboard Laporan</div>
            </a>
        </li>

        <li class="menu-item <?= strpos(current_url(), 'rekap_antrian') !== false ? 'active' : '' ?>">
            <a href="<?= base_url('laporan/rekap_antrian'); ?>" class="menu-link">
                <i class="menu-icon ri ri-calendar-check-line"></i>
                <div>Rekap Per Hari</div>
            </a>
        </li>

        <li class="menu-item <?= strpos(current_url(), 'jam_sibuk') !== false ? 'active' : '' ?>">
            <a href="<?= base_url('laporan/jam_sibuk'); ?>" class="menu-link">
                <i class="menu-icon ri ri-timer-flash-line"></i>
                <div>Jam Sibuk</div>
            </a>
        </li>

        <li class="menu-item <?= strpos(current_url(), 'sla_layanan') !== false ? 'active' : '' ?>">
            <a href="<?= base_url('laporan/sla_layanan'); ?>" class="menu-link">
                <i class="menu-icon ri ri-speed-up-line"></i>
                <div>SLA Layanan</div>
            </a>
        </li>

        <li class="menu-item <?= strpos(current_url(), 'no_show') !== false ? 'active' : '' ?>">
            <a href="<?= base_url('laporan/no_show'); ?>" class="menu-link">
                <i class="menu-icon ri ri-user-unfollow-line"></i>
                <div>No-Show</div>
            </a>
        </li>

        <li class="menu-item <?= strpos(current_url(), 'kinerja_petugas') !== false ? 'active' : '' ?>">
            <a href="<?= base_url('laporan/kinerja_petugas'); ?>" class="menu-link">
                <i class="menu-icon ri ri-team-line"></i>
                <div>Kinerja Petugas</div>
            </a>
        </li>

        <li class="menu-item <?= strpos(current_url(), 'tren_antrian') !== false ? 'active' : '' ?>">
            <a href="<?= base_url('laporan/tren_antrian'); ?>" class="menu-link">
                <i class="menu-icon ri ri-line-chart-line"></i>
                <div>Tren Harian/Bulanan</div>
            </a>
        </li>

        <li class="menu-item <?= strpos(current_url(), 'konversi_antrian') !== false ? 'active' : '' ?>">
            <a href="<?= base_url('laporan/konversi_antrian'); ?>" class="menu-link">
                <i class="menu-icon ri ri-funnel-line"></i>
                <div>Konversi Antrian</div>
            </a>
        </li>

        <li class="menu-item <?= strpos(current_url(), 'detail_antrian') !== false ? 'active' : '' ?>">
            <a href="<?= base_url('laporan/detail_antrian'); ?>" class="menu-link">
                <i class="menu-icon ri ri-list-check-2"></i>
                <div>Detail Antrian</div>
            </a>
        </li>

        <li class="menu-item <?= strpos(current_url(), 'detail_hasil_layanan') !== false ? 'active' : '' ?>">
            <a href="<?= base_url('laporan/detail_hasil_layanan'); ?>" class="menu-link">
                <i class="menu-icon ri ri-file-text-line"></i>
                <div>Hasil Layanan</div>
            </a>
        </li>

        <li class="menu-item <?= strpos(current_url(), 'waktu_layanan') !== false ? 'active' : '' ?>">
            <a href="<?= base_url('laporan/waktu_layanan'); ?>" class="menu-link">
                <i class="menu-icon ri ri-timer-line"></i>
                <div>Waktu Layanan</div>
            </a>
        </li>

        <li class="menu-item <?= strpos(current_url(), 'kepadatan_sektor') !== false ? 'active' : '' ?>">
            <a href="<?= base_url('laporan/kepadatan_sektor'); ?>" class="menu-link">
                <i class="menu-icon ri ri-layout-grid-line"></i>
                <div>Kepadatan Sektor</div>
            </a>
        </li>

    </ul>
</li>


                <?php elseif ($role_id == 2): // ADMIN LAYANAN 
                ?>
    <li class="menu-header">Admin Layanan</li>

    <li class="menu-item">
        <a href="<?= base_url('admin_layanan/antrian_hari_ini'); ?>" class="menu-link">
            <i class="menu-icon ri ri-time-line"></i>
            <div>Antrian Hari Ini</div>
        </a>
    </li>

    <li class="menu-item">
        <a href="<?= base_url('admin_layanan/riwayat_antrian'); ?>" class="menu-link">
            <i class="menu-icon ri ri-history-line"></i>
            <div>Riwayat Antrian</div>
        </a>
    </li>

    <li class="menu-item <?= strpos(current_url(), 'laporan') !== false ? 'open active' : '' ?>">
        <a href="javascript:void(0);" class="menu-link menu-toggle">
            <i class="menu-icon ri ri-bar-chart-grouped-line"></i>
            <div>Laporan</div>
        </a>
        <ul class="menu-sub">
            <li class="menu-item <?= strpos(current_url(), 'laporan/dashboard') !== false ? 'active' : '' ?>">
                <a href="<?= base_url('laporan/dashboard'); ?>" class="menu-link">
                    <i class="menu-icon ri ri-dashboard-line"></i>
                    <div>Dashboard Laporan</div>
                </a>
            </li>
            <li class="menu-item <?= strpos(current_url(), 'rekap_antrian') !== false ? 'active' : '' ?>">
                <a href="<?= base_url('laporan/rekap_antrian'); ?>" class="menu-link">
                    <i class="menu-icon ri ri-calendar-check-line"></i>
                    <div>Rekap Per Hari</div>
                </a>
            </li>
            <li class="menu-item <?= strpos(current_url(), 'jam_sibuk') !== false ? 'active' : '' ?>">
                <a href="<?= base_url('laporan/jam_sibuk'); ?>" class="menu-link">
                    <i class="menu-icon ri ri-timer-flash-line"></i>
                    <div>Jam Sibuk</div>
                </a>
            </li>
            <li class="menu-item <?= strpos(current_url(), 'sla_layanan') !== false ? 'active' : '' ?>">
                <a href="<?= base_url('laporan/sla_layanan'); ?>" class="menu-link">
                    <i class="menu-icon ri ri-speed-up-line"></i>
                    <div>SLA Layanan</div>
                </a>
            </li>
            <li class="menu-item <?= strpos(current_url(), 'no_show') !== false ? 'active' : '' ?>">
                <a href="<?= base_url('laporan/no_show'); ?>" class="menu-link">
                    <i class="menu-icon ri ri-user-unfollow-line"></i>
                    <div>No-Show</div>
                </a>
            </li>
            <li class="menu-item <?= strpos(current_url(), 'kinerja_petugas') !== false ? 'active' : '' ?>">
                <a href="<?= base_url('laporan/kinerja_petugas'); ?>" class="menu-link">
                    <i class="menu-icon ri ri-team-line"></i>
                    <div>Kinerja Petugas</div>
                </a>
            </li>
            <li class="menu-item <?= strpos(current_url(), 'tren_antrian') !== false ? 'active' : '' ?>">
                <a href="<?= base_url('laporan/tren_antrian'); ?>" class="menu-link">
                    <i class="menu-icon ri ri-line-chart-line"></i>
                    <div>Tren Harian/Bulanan</div>
                </a>
            </li>
            <li class="menu-item <?= strpos(current_url(), 'konversi_antrian') !== false ? 'active' : '' ?>">
                <a href="<?= base_url('laporan/konversi_antrian'); ?>" class="menu-link">
                    <i class="menu-icon ri ri-funnel-line"></i>
                    <div>Konversi Antrian</div>
                </a>
            </li>
            <li class="menu-item <?= strpos(current_url(), 'detail_antrian') !== false ? 'active' : '' ?>">
                <a href="<?= base_url('laporan/detail_antrian'); ?>" class="menu-link">
                    <i class="menu-icon ri ri-list-check-2"></i>
                    <div>Detail Antrian</div>
                </a>
            </li>
            <li class="menu-item <?= strpos(current_url(), 'detail_hasil_layanan') !== false ? 'active' : '' ?>">
                <a href="<?= base_url('laporan/detail_hasil_layanan'); ?>" class="menu-link">
                    <i class="menu-icon ri ri-file-text-line"></i>
                    <div>Hasil Layanan</div>
                </a>
            </li>
            <li class="menu-item <?= strpos(current_url(), 'waktu_layanan') !== false ? 'active' : '' ?>">
                <a href="<?= base_url('laporan/waktu_layanan'); ?>" class="menu-link">
                    <i class="menu-icon ri ri-timer-line"></i>
                    <div>Waktu Layanan</div>
                </a>
            </li>
            <li class="menu-item <?= strpos(current_url(), 'kepadatan_sektor') !== false ? 'active' : '' ?>">
                <a href="<?= base_url('laporan/kepadatan_sektor'); ?>" class="menu-link">
                    <i class="menu-icon ri ri-layout-grid-line"></i>
                    <div>Kepadatan Sektor</div>
                </a>
            </li>
        </ul>
    </li>

                <?php elseif ($role_id == 4): // MASYARAKAT 
                ?>
                    <li class="menu-header">Layanan Publik</li>

                    <li class="menu-item">
                        <a href="<?= base_url('masyarakat/daftar_antrian'); ?>" class="menu-link">
                            <i class="menu-icon ri ri-add-box-line"></i>
                            <div>Daftar Antrian</div>
                        </a>
                    </li>

                    <li class="menu-item">
                        <a href="<?= base_url('masyarakat/antrian_saya'); ?>" class="menu-link">
                            <i class="menu-icon ri ri-ticket-2-line"></i>
                            <div>Antrian Saya</div>
                        </a>
                    </li>


                    <li class="menu-item">
                        <a href="<?= base_url('masyarakat/riwayat_antrian'); ?>" class="menu-link">
                            <i class="menu-icon ri ri-history-line"></i>
                            <div>Riwayat Antrian</div>
                        </a>
                    </li>

                <?php endif; ?>

                <!-- Separator -->
                <li class="menu-header mt-3">Pengguna</li>

                <li class="menu-item">
                    <a href="<?= base_url('auth/logout'); ?>" class="menu-link">
                        <i class="menu-icon ri ri-logout-box-line"></i>
                        <div>Logout</div>
                    </a>
                </li>

            </ul>

        </aside>
        <!-- / Menu -->

        <!-- Layout container -->
        <div class="layout-page">
            <nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme">
                <div class="container-fluid portal-navbar-inner">

                    <!-- TOGGLE SIDEBAR (MOBILE ONLY) -->
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center d-xl-none">
                        <a class="nav-item nav-link px-0" href="javascript:void(0)">
                            <i class="ri ri-menu-line ri-22px"></i>
                        </a>
                    </div>

                    <div class="portal-navbar-title">
                        <span class="portal-navbar-badge">
                            <i class="ri ri-government-line"></i>
                        </span>
                        <div>
                            <small>Portal Pelayanan</small>
                            <strong>MPP Rembang</strong>
                        </div>
                    </div>

                    <div class="portal-navbar-actions">
                        <div class="portal-user-chip">
                            <span class="portal-user-avatar"><?= $display_initial ?></span>
                            <span class="portal-user-meta">
                                <strong><?= html_escape($display_name) ?></strong>
                                <span><?= html_escape($role_label) ?></span>
                            </span>
                        </div>

                        <a href="<?= base_url('auth/logout'); ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="ri ri-logout-box-line me-1"></i> Logout
                        </a>
                    </div>

                </div>
            </nav>

        <?php if ($this->session->userdata('role_id') == 2): ?>
        <div class="container-fluid mt-3">
        <div class="alert portal-admin-banner shadow-sm d-flex justify-content-between align-items-center flex-column flex-md-row gap-3">
            <div>
            <strong>Halo, <?= $this->session->userdata('nama_lengkap') ?>!</strong><br>
            Anda bertugas di instansi:
            <span class="fw-bold"><?= $this->session->userdata('nama_instansi') ?></span>
            </div>

            <a href="<?= base_url('admin_layanan/antrian_hari_ini') ?>"
            class="btn btn-light btn-sm">
            <i class="ri ri-list-check"></i> Lihat Antrian
            </a>
        </div>
        </div>
        <?php endif; ?>

            <!-- Navbar -->
            <!-- <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
                id="layout-navbar">
                <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                    <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                        <i class="icon-base ri ri-menu-line icon-md"></i>
                    </a>
                </div> -->
            <!-- </nav> -->

            <!-- / Navbar -->

<?php
defined( 'ABSPATH' ) || exit;

class SF_Locker_Admin_Page {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
        add_action( 'admin_post_sf_import_pdf', array( __CLASS__, 'handle_pdf_import' ) );
        add_action( 'admin_post_sf_fetch_pdf', array( __CLASS__, 'handle_fetch_pdf' ) );
        add_action( 'admin_post_sf_export_lockers', array( __CLASS__, 'handle_export' ) );
        add_action( 'admin_post_sf_clear_lockers', array( __CLASS__, 'handle_clear' ) );
        add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
    }

    public static function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            '順豐智能櫃',
            '順豐智能櫃',
            'manage_woocommerce',
            'sf-express-locker',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function enqueue_admin_assets( $hook ) {
        if ( 'woocommerce_page_sf-express-locker' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wp-admin' );
    }

    public static function render_page() {
        $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'lockers';
        ?>
        <div class="wrap">
            <h1>順豐智能櫃管理</h1>
            <h2 class="nav-tab-wrapper">
                <a href="?page=sf-express-locker&tab=lockers" class="nav-tab <?php echo $tab === 'lockers' ? 'nav-tab-active' : ''; ?>">
                    智能櫃列表 (<?php echo SF_Locker_Data::get_count(); ?>)
                </a>
                <a href="?page=sf-express-locker&tab=import" class="nav-tab <?php echo $tab === 'import' ? 'nav-tab-active' : ''; ?>">
                    匯入資料
                </a>
                <a href="?page=sf-express-locker&tab=settings" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    設定
                </a>
            </h2>

            <?php
            if ( 'import' === $tab ) {
                self::render_import_tab();
            } elseif ( 'settings' === $tab ) {
                self::render_settings_tab();
            } else {
                self::render_lockers_tab();
            }
            ?>
        </div>
        <?php
    }

    private static function render_lockers_tab() {
        $page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
        $per_page = 100;
        $offset   = ( $page - 1 ) * $per_page;
        $lockers  = SF_Locker_Data::get_all( $offset, $per_page );
        $total    = SF_Locker_Data::get_count();
        $pages    = ceil( $total / $per_page );

        ?>
        <p>顯示智能櫃資料。可透過「匯入資料」頁籤上傳順豐官方 PDF 更新。</p>
        <div style="display:flex;gap:8px;margin-bottom:12px;">
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
                <input type="hidden" name="action" value="sf_export_lockers">
                <?php submit_button( '匯出 JSON', 'secondary', 'export_btn', false ); ?>
            </form>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" onsubmit="return confirm('確定要清除所有智能櫃資料？此操作無法復原。');">
                <input type="hidden" name="action" value="sf_clear_lockers">
                <?php wp_nonce_field( 'sf_clear_lockers' ); ?>
                <?php submit_button( '清除所有資料', 'delete', 'clear_btn', false ); ?>
            </form>
        </div>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>編碼</th>
                    <th>地區</th>
                    <th>區域</th>
                    <th>地址</th>
                    <th>營業時間</th>
                    <th>狀態</th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $lockers ) ) : ?>
                    <tr><td colspan="6">尚無資料。請匯入智能櫃資料。</td></tr>
                <?php else : ?>
                    <?php foreach ( $lockers as $l ) : ?>
                        <tr>
                            <td><code><?php echo esc_html( $l['code'] ); ?></code></td>
                            <td><?php echo esc_html( $l['district'] ); ?></td>
                            <td><?php echo esc_html( $l['region'] ); ?></td>
                            <td><?php echo esc_html( $l['address_zh'] ); ?></td>
                            <td><?php echo esc_html( $l['opening_hours'] ); ?></td>
                            <td><?php echo $l['is_active'] ? '啟用' : '停用'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ( $pages > 1 ) : ?>
            <div class="tablenav" style="margin-top:12px;">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links( array(
                        'base'      => add_query_arg( 'paged', '%#%' ),
                        'format'    => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total'     => $pages,
                        'current'   => $page,
                    ) );
                    ?>
                </div>
            </div>
        <?php endif; ?>
        <?php
    }

    private static function render_import_tab() {
        $pdf_available = class_exists( 'Smalot\PdfParser\Parser' );
        $last_updated = get_option( 'sf_locker_last_updated', '' );
        $pdf_url = SF_LOCKER_PDF_URL;
        ?>
        <div class="card" style="max-width: 600px; margin-top: 16px;">
            <h2>匯入智能櫃資料</h2>

            <p>
                資料來源：<a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank">順豐官方智能櫃清單 PDF</a>
                <?php if ( $last_updated ) : ?>
                    <br>上次更新：<?php echo esc_html( $last_updated ); ?>
                <?php endif; ?>
            </p>

            <?php if ( isset( $_GET['import_result'] ) ) : ?>
                <?php
                $result = get_transient( 'sf_locker_import_result' );
                delete_transient( 'sf_locker_import_result' );
                ?>
                <?php if ( $result ) : ?>
                    <div class="notice notice-success is-dismissible">
                        <p>匯入完成！新增 <?php echo $result['inserted']; ?> 個，更新 <?php echo $result['updated']; ?> 個，跳過 <?php echo $result['skipped']; ?> 個。</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( isset( $_GET['fetch_error'] ) ) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html( wp_unslash( $_GET['fetch_error'] ) ); ?></p>
                </div>
            <?php endif; ?>

            <?php if ( ! $pdf_available ) : ?>
                <div class="notice notice-warning">
                    <p>PDF 解析器未安裝。請在插件目錄執行 <code>composer install</code> 安裝依賴，或使用下方手動上傳 JSON 方式。</p>
                </div>
            <?php endif; ?>

            <?php if ( $pdf_available ) : ?>
                <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="margin-bottom: 20px;">
                    <input type="hidden" name="action" value="sf_fetch_pdf">
                    <?php wp_nonce_field( 'sf_fetch_pdf' ); ?>
                    <p>
                        <button type="submit" class="button button-primary" onclick="return confirm('將會從順豐官網下載最新智能櫃清單並更新資料，確定繼續？');">
                            從順豐官網更新資料
                        </button>
                    </p>
                </form>
            <?php endif; ?>

            <hr>

            <h3>手動上傳檔案</h3>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="sf_import_pdf">
                <?php wp_nonce_field( 'sf_import_pdf' ); ?>

                <p>
                    <label for="import_file">選擇檔案</label>
                    <input type="file" name="import_file" id="import_file" accept=".pdf,.json" style="display:inline;">
                    <br>
                    <span class="description">上傳順豐官方 PDF（智能櫃清單）或 JSON 檔案。</span>
                </p>

                <?php submit_button( '上傳並匯入', 'secondary', 'submit_import' ); ?>
            </form>
        </div>

        <div class="card" style="max-width: 600px; margin-top: 16px;">
            <h2>重新載入預設資料</h2>
            <p>若資料庫為空，可點擊下方按鈕載入插件預設的智能櫃資料。</p>
            <form method="post">
                <input type="hidden" name="sf_reload_defaults" value="1">
                <?php wp_nonce_field( 'sf_reload_defaults' ); ?>
                <?php submit_button( '載入預設資料', 'secondary', 'reload_defaults', false ); ?>
            </form>
        </div>
        <?php

        if ( isset( $_POST['sf_reload_defaults'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'sf_reload_defaults' ) ) {
            SF_Locker_Data::import_bundled_data();
            echo '<div class="notice notice-success is-dismissible"><p>已載入預設資料。</p></div>';
        }
    }

    private static function render_settings_tab() {
        ?>
        <div class="card" style="max-width: 600px; margin-top: 16px;">
            <h2>設定</h2>
            <p>運費設定請前往 <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=shipping' ); ?>">WooCommerce → 設定 → 運送</a>，
            選擇運送區域中的「順豐智能櫃自取」進行設定。</p>

            <hr>
            <h3>系統資訊</h3>
            <table class="form-table">
                <tr>
                    <th>插件版本</th>
                    <td><?php echo SF_LOCKER_VERSION; ?></td>
                </tr>
                <tr>
                    <th>智能櫃總數</th>
                    <td><?php echo SF_Locker_Data::get_count(); ?> 個</td>
                </tr>
                <tr>
                    <th>PDF 解析器</th>
                    <td><?php echo class_exists( 'Smalot\PdfParser\Parser' ) ? '已安裝' : '未安裝'; ?></td>
                </tr>
                <tr>
                    <th>資料庫表格</th>
                    <td><code><?php global $wpdb; echo $wpdb->prefix . SF_LOCKER_TABLE; ?></code></td>
                </tr>
            </table>
        </div>
        <?php
    }

    public static function handle_pdf_import() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( '權限不足' );
        }

        check_admin_referer( 'sf_import_pdf' );

        if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_die( '檔案上傳失敗，請重試。' );
        }

        $file = $_FILES['import_file'];
        $ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

        if ( $ext === 'json' ) {
            $json = file_get_contents( $file['tmp_name'] );
            $lockers = json_decode( $json, true );
            if ( empty( $lockers ) ) {
                wp_die( 'JSON 格式無效。' );
            }
            $result = SF_Locker_Data::import_batch( $lockers );
        } elseif ( $ext === 'pdf' ) {
            $importer = new SF_Locker_PDF_Importer();
            if ( ! $importer->is_available() ) {
                wp_die( 'PDF 解析器未安裝。請在插件目錄執行 composer install。' );
            }
            $result = $importer->import( $file['tmp_name'] );
            if ( false === $result ) {
                $errors = $importer->get_errors();
                wp_die( '匯入失敗：' . implode( '<br>', $errors ) );
            }
        } else {
            wp_die( '只接受 PDF 或 JSON 檔案。' );
        }

        update_option( 'sf_locker_last_updated', current_time( 'Y-m-d H:i:s' ) );
        set_transient( 'sf_locker_import_result', $result, 60 );
        wp_redirect( admin_url( 'admin.php?page=sf-express-locker&tab=import&import_result=1' ) );
        exit;
    }

    public static function handle_fetch_pdf() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( '權限不足' );
        }

        check_admin_referer( 'sf_fetch_pdf' );

        $importer = new SF_Locker_PDF_Importer();
        if ( ! $importer->is_available() ) {
            wp_die( 'PDF 解析器未安裝。請在插件目錄執行 composer install。' );
        }

        if ( ! function_exists( 'download_url' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $pdf_url = SF_LOCKER_PDF_URL;

        $tmp_file = download_url( $pdf_url, 60 );
        if ( is_wp_error( $tmp_file ) ) {
            wp_redirect( admin_url( 'admin.php?page=sf-express-locker&tab=import&fetch_error=' . urlencode( '下載 PDF 失敗：' . $tmp_file->get_error_message() ) ) );
            exit;
        }

        $result = $importer->import( $tmp_file );
        @unlink( $tmp_file );

        if ( false === $result ) {
            $errors = $importer->get_errors();
            wp_redirect( admin_url( 'admin.php?page=sf-express-locker&tab=import&fetch_error=' . urlencode( '匯入失敗：' . implode( ', ', $errors ) ) ) );
            exit;
        }

        update_option( 'sf_locker_last_updated', current_time( 'Y-m-d H:i:s' ) );
        set_transient( 'sf_locker_import_result', $result, 60 );
        wp_redirect( admin_url( 'admin.php?page=sf-express-locker&tab=import&import_result=1' ) );
        exit;
    }

    public static function handle_export() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( '權限不足' );
        }

        $lockers = SF_Locker_Data::get_all( 0, 99999 );

        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="sf-lockers-export.json"' );
        echo json_encode( $lockers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        exit;
    }

    public static function handle_clear() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( '權限不足' );
        }

        check_admin_referer( 'sf_clear_lockers' );

        global $wpdb;
        $table_name = $wpdb->prefix . SF_LOCKER_TABLE;
        $wpdb->query( "TRUNCATE TABLE {$table_name}" );
        delete_option( 'sf_locker_last_updated' );

        set_transient( 'sf_locker_cleared', 1, 60 );
        wp_redirect( admin_url( 'admin.php?page=sf-express-locker&tab=lockers&cleared=1' ) );
        exit;
    }

    public static function admin_notices() {
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'sf-express-locker' && isset( $_GET['cleared'] ) ) {
            $cleared = get_transient( 'sf_locker_cleared' );
            delete_transient( 'sf_locker_cleared' );
            if ( $cleared ) {
                echo '<div class="notice notice-success is-dismissible"><p>所有智能櫃資料已清除。</p></div>';
            }
        }
    }
}

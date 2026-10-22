<?php
/**
 * Main WordPress administration overview for custom Gravity Forms views.
 *
 * @package Gravity_Form_Custom_View
 */
if (!defined('ABSPATH')) {
    exit;
}

$form_lookup = array();
foreach ((array) $forms as $form) {
    if (isset($form['id'])) {
        $form_lookup[(int) $form['id']] = (string) ($form['title'] ?? '');
    }
}

$view_count = count((array) $views);
$used_form_ids = array();
$sms_count = 0;
$restricted_count = 0;
foreach ((array) $views as $view_item) {
    $used_form_ids[(int) $view_item->form_id] = true;
    if (!empty($view_item->admin_sms_pattern) || !empty($view_item->user_sms_pattern)) {
        $sms_count++;
    }
    if (!empty($view_item->user_access_ids)) {
        $restricted_count++;
    }
}
$used_form_count = count($used_form_ids);
$add_url = admin_url('admin.php?page=gf_custom_view&action=add');
?>
<div class="wrap gfcv-admin-wrap gfcv-dashboard" dir="rtl">
    <section class="gfcv-dashboard-hero" aria-labelledby="gfcv-dashboard-title">
        <div class="gfcv-dashboard-hero-copy">
            <div class="gfcv-dashboard-kicker">
                <span class="dashicons dashicons-forms" aria-hidden="true"></span>
                مدیریت نمایش رکوردهای گرویتی فرم
            </div>
            <h1 id="gfcv-dashboard-title">لیست‌های نمایشی</h1>
            <p>لیست‌های متصل به فرم‌ها را از همین‌جا مدیریت کنید، کد کوتاه آن‌ها را بردارید و تنظیمات دسترسی و پیامک را ویرایش کنید.</p>
        </div>
        <div class="gfcv-dashboard-hero-actions">
            <a href="#gfcv-dashboard-outbox" id="gfcv-show-outbox" class="button button-secondary" aria-controls="gfcv-dashboard-outbox" aria-expanded="false">
                <span class="dashicons dashicons-email-alt" aria-hidden="true"></span>
                گزارش پیامک‌های ارسالی
            </a>
            <a href="#gfcv-service-settings" class="button button-secondary">تنظیمات پیامک</a>
            <a href="<?php echo esc_url($add_url); ?>" class="button button-primary gfcv-dashboard-create">
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                ایجاد لیست جدید
            </a>
        </div>
    </section>

    <?php if (GFCV_Kavenegar::get_key() === '') : ?>
        <div class="notice notice-warning inline">
            <p><strong>سرویس پیامک آماده نیست.</strong> کلید API سراسری کاوه‌نگار ثبت نشده است؛
            ارسال پیامک تستی و دریافت گزارش تا ثبت آن امکان‌پذیر نیست.
            <a href="#gfcv-service-settings">تنظیم کلید API</a></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])) : ?>
        <div class="notice notice-success is-dismissible"><p>نمای سفارشی با موفقیت حذف شد.</p></div>
    <?php endif; ?>

    <section class="gfcv-dashboard-stats" aria-label="آمار لیست‌های نمایشی">
        <div class="gfcv-stat-card">
            <span class="gfcv-stat-icon" aria-hidden="true"><span class="dashicons dashicons-list-view"></span></span>
            <div class="gfcv-stat-content">
                <span class="gfcv-stat-label">لیست‌های ایجادشده</span>
                <strong class="gfcv-stat-value"><?php echo esc_html(number_format_i18n($view_count)); ?></strong>
            </div>
        </div>
        <div class="gfcv-stat-card">
            <span class="gfcv-stat-icon" aria-hidden="true"><span class="dashicons dashicons-feedback"></span></span>
            <div class="gfcv-stat-content">
                <span class="gfcv-stat-label">فرم‌های متصل</span>
                <strong class="gfcv-stat-value"><?php echo esc_html(number_format_i18n($used_form_count)); ?></strong>
            </div>
        </div>
        <div class="gfcv-stat-card">
            <span class="gfcv-stat-icon" aria-hidden="true"><span class="dashicons dashicons-email-alt"></span></span>
            <div class="gfcv-stat-content">
                <span class="gfcv-stat-label">لیست‌های دارای الگوی پیامک</span>
                <strong class="gfcv-stat-value"><?php echo esc_html(number_format_i18n($sms_count)); ?></strong>
            </div>
        </div>
        <div class="gfcv-stat-card">
            <span class="gfcv-stat-icon" aria-hidden="true"><span class="dashicons dashicons-lock"></span></span>
            <div class="gfcv-stat-content">
                <span class="gfcv-stat-label">لیست‌های دارای محدودیت کاربران</span>
                <strong class="gfcv-stat-value"><?php echo esc_html(number_format_i18n($restricted_count)); ?></strong>
            </div>
        </div>
    </section>

    <details class="gfcv-dashboard-outbox" id="gfcv-dashboard-outbox" aria-labelledby="gfcv-outbox-heading">
        <summary class="gfcv-outbox-summary">گزارش پیامک‌های ارسالی <span class="gfcv-outbox-summary-hint">برای بازکردن گزارش کلیک کنید</span></summary>
        <div class="gfcv-dashboard-outbox-head">
            <div>
                <h2 id="gfcv-outbox-heading">گزارش پیامک‌های ارسالی کاوه‌نگار</h2>
                <p>گزارش از حساب کاوه‌نگار خوانده می‌شود و ممکن است شامل پیامک‌هایی خارج از این افزونه نیز باشد.</p>
                <p>اگر پس از بازکردن گزارش، خطای سرویس رخ دهد، جزئیات قابل‌فهم آن همین‌جا نمایش داده می‌شود.</p>
            </div>
            <button type="button" id="gfcv-close-outbox" class="button button-secondary" aria-label="بستن گزارش پیامک‌ها">بستن</button>
        </div>
        <form id="gfcv-outbox-filters" class="gfcv-dashboard-outbox-toolbar">
            <label>نوع گزارش
                <select id="gfcv-outbox-mode" aria-label="نوع گزارش">
                    <option value="latest">۱۰۰ پیامک اخیر</option>
                    <option value="range">بازه تاریخ</option>
                </select>
            </label>
            <label class="gfcv-outbox-range-field" hidden>از تاریخ میلادی
                <input type="date" id="gfcv-outbox-from" autocomplete="off">
            </label>
            <label class="gfcv-outbox-range-field" hidden>تا تاریخ میلادی
                <input type="date" id="gfcv-outbox-to" autocomplete="off">
            </label>
            <button type="submit" id="gfcv-fetch-outbox" class="button button-primary">نمایش گزارش</button>
            <span class="description">بازه انتخابی حداکثر ۳۱ روز باشد.</span>
        </form>
        <div id="gfcv-outbox-status" class="gfcv-outbox-status" role="status" aria-live="polite"></div>
        <div class="gfcv-outbox-scroll">
            <table class="wp-list-table widefat" id="gfcv-outbox-table" hidden>
                <thead>
                    <tr>
                        <th scope="col">شناسه پیامک</th>
                        <th scope="col">تاریخ</th>
                        <th scope="col">گیرنده</th>
                        <th scope="col">فرستنده</th>
                        <th scope="col">متن پیام</th>
                        <th scope="col">وضعیت</th>
                    </tr>
                </thead>
                <tbody id="gfcv-outbox-rows"></tbody>
            </table>
        </div>
        <div class="gfcv-outbox-footer" id="gfcv-outbox-pagination" hidden>
            <button type="button" class="button button-secondary" id="gfcv-outbox-prev">صفحه قبل</button>
            <span id="gfcv-outbox-page-label" aria-live="polite"></span>
            <button type="button" class="button button-secondary" id="gfcv-outbox-next">صفحه بعد</button>
        </div>
    </details>

    <section class="gfcv-dashboard-list-panel" aria-labelledby="gfcv-views-heading">
        <div class="gfcv-dashboard-panel-head">
            <div>
                <h2 id="gfcv-views-heading">مدیریت لیست‌ها</h2>
                <p>برای ویرایش تنظیمات هر لیست، عنوان آن را انتخاب کنید.</p>
            </div>
            <span class="gfcv-dashboard-result-count" aria-live="polite">
                <span id="gfcv-visible-count"><?php echo esc_html(number_format_i18n($view_count)); ?></span> لیست
            </span>
        </div>

        <?php if ($view_count > 0) : ?>
            <div class="gfcv-dashboard-toolbar">
                <div class="gfcv-dashboard-search">
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <label class="screen-reader-text" for="gfcv-view-search">جست‌وجو در لیست‌ها</label>
                    <input type="search" id="gfcv-view-search" placeholder="جست‌وجوی عنوان، فرم یا شناسه..." autocomplete="off">
                </div>
                <div class="gfcv-dashboard-filters" role="group" aria-label="فیلتر فهرست">
                    <button type="button" class="gfcv-filter-button is-active" data-filter="all" aria-pressed="true">همه</button>
                    <button type="button" class="gfcv-filter-button" data-filter="sms" aria-pressed="false">دارای پیامک</button>
                    <button type="button" class="gfcv-filter-button" data-filter="restricted" aria-pressed="false">دسترسی محدود</button>
                </div>
            </div>

            <div class="gfcv-dashboard-table-scroll" tabindex="0" role="region" aria-label="جدول لیست‌های نمایشی، قابل پیمایش افقی">
                <table class="wp-list-table widefat gfcv-views-table">
                    <thead>
                        <tr>
                            <th scope="col" class="gfcv-table-name">لیست نمایشی</th>
                            <th scope="col">فرم مرتبط</th>
                            <th scope="col">کد کوتاه</th>
                            <th scope="col">سطح دسترسی</th>
                            <th scope="col">تاریخ ایجاد</th>
                            <th scope="col" class="gfcv-table-actions">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($views as $view) :
                            $form_id = (int) $view->form_id;
                            $form_name = isset($form_lookup[$form_id]) && $form_lookup[$form_id] !== ''
                                ? $form_lookup[$form_id] : 'فرم شماره ' . $form_id;
                            $has_sms = !empty($view->admin_sms_pattern) || !empty($view->user_sms_pattern);
                            $is_restricted = !empty($view->user_access_ids);
                            $shortcode = '[gf-custom-view id=' . absint($view->id) . ']';
                            $edit_url = admin_url('admin.php?page=gf_custom_view&action=edit&view_id=' . absint($view->id));
                            $search_terms = implode(' ', array($view->view_title, $form_name, (string) $form_id, (string) $view->id));
                        ?>
                            <tr class="gfcv-dashboard-view-row"
                                data-search="<?php echo esc_attr($search_terms); ?>"
                                data-has-sms="<?php echo $has_sms ? '1' : '0'; ?>"
                                data-is-restricted="<?php echo $is_restricted ? '1' : '0'; ?>">
                                <td data-label="لیست نمایشی">
                                    <div class="gfcv-view-main">
                                        <span class="gfcv-view-avatar" aria-hidden="true"><span class="dashicons dashicons-media-text"></span></span>
                                        <div class="gfcv-view-summary">
                                            <a class="gfcv-view-title" href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($view->view_title); ?></a>
                                            <div class="gfcv-view-meta">
                                                <span>شناسه <?php echo esc_html(number_format_i18n($view->id)); ?></span>
                                                <?php if ($has_sms) : ?>
                                                    <span class="gfcv-view-dot" aria-hidden="true">·</span>
                                                    <span>الگوی پیامک دارد</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="فرم مرتبط">
                                    <span class="gfcv-form-name"><?php echo esc_html($form_name); ?></span>
                                    <span class="gfcv-form-id">شناسه فرم: <?php echo esc_html(number_format_i18n($form_id)); ?></span>
                                </td>
                                <td data-label="کد کوتاه">
                                    <div class="gfcv-shortcode-field">
                                        <code dir="ltr"><?php echo esc_html($shortcode); ?></code>
                                        <button type="button" class="gfcv-shortcode-copy" data-shortcode="<?php echo esc_attr($shortcode); ?>" aria-label="کپی کد کوتاه <?php echo esc_attr($view->view_title); ?>" title="کپی کد کوتاه">
                                            <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                                            <span class="gfcv-copy-text">کپی</span>
                                        </button>
                                    </div>
                                </td>
                                <td data-label="سطح دسترسی">
                                    <?php if ($is_restricted) : ?>
                                        <span class="gfcv-access-tag gfcv-access-restricted"><span class="dashicons dashicons-lock" aria-hidden="true"></span> محدود</span>
                                    <?php else : ?>
                                        <span class="gfcv-access-tag gfcv-access-public"><span class="dashicons dashicons-unlock" aria-hidden="true"></span> عمومی</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="تاریخ ایجاد" class="gfcv-created-at">
                                    <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($view->date_created))); ?>
                                </td>
                                <td data-label="عملیات">
                                    <div class="gfcv-view-actions">
                                        <a class="button button-secondary gfcv-edit-action" href="<?php echo esc_url($edit_url); ?>">
                                            <span class="dashicons dashicons-edit" aria-hidden="true"></span> ویرایش
                                        </a>
                                        <form method="post" class="gfcv-delete-view-form" data-confirm="<?php echo esc_attr('آیا از حذف لیست «' . $view->view_title . '» اطمینان دارید؟ این کار قابل بازگشت نیست.'); ?>">
                                            <?php wp_nonce_field('gfcv_save_view', 'gfcv_nonce'); ?>
                                            <input type="hidden" name="gfcv_action" value="delete_view">
                                            <input type="hidden" name="view_id" value="<?php echo esc_attr($view->id); ?>">
                                            <button type="submit" class="gfcv-delete-action" aria-label="حذف <?php echo esc_attr($view->view_title); ?>" title="حذف لیست">
                                                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="gfcv-filter-empty" class="gfcv-dashboard-filter-empty" hidden>
                <span class="dashicons dashicons-search" aria-hidden="true"></span>
                <strong>نتیجه‌ای پیدا نشد</strong>
                <p>عبارت جست‌وجو یا فیلتر انتخابی را تغییر دهید.</p>
                <button type="button" id="gfcv-clear-filters" class="button button-secondary">پاک‌کردن فیلترها</button>
            </div>
        <?php else : ?>
            <div class="gfcv-dashboard-empty">
                <span class="gfcv-empty-icon" aria-hidden="true"><span class="dashicons dashicons-welcome-add-page"></span></span>
                <h3>هنوز لیستی ایجاد نکرده‌اید</h3>
                <p>اولین لیست نمایشی را بسازید تا بتوانید رکوردهای فرم را با سطح دسترسی دلخواه نمایش دهید.</p>
                <a href="<?php echo esc_url($add_url); ?>" class="button button-primary">ایجاد اولین لیست</a>
            </div>
        <?php endif; ?>
    </section>

    <div class="gfcv-dashboard-settings-heading" id="gfcv-settings">
        <h2>تنظیمات افزونه</h2>
        <p>اتصال سرویس پیامک و فونت سفارشی گزارش‌ها در این بخش مدیریت می‌شوند.</p>
    </div>

    <details class="gfcv-service-settings" id="gfcv-service-settings" <?php echo (isset($_GET['api_error']) || isset($_GET['api_updated']) || get_option(GFCV_Kavenegar::MIGRATION_KEY)==='conflict') ? 'open' : ''; ?>>
        <summary><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span> تنظیمات سرویس پیامک کاوه‌نگار</summary>
        <div class="gfcv-service-settings-body">
            <h3>کلید API مشترک تمام لیست‌ها</h3>
            <p class="description">یک کلید API برای تمام لیست‌ها و ارسال‌های آزمایشی استفاده می‌شود. برای حفظ امنیت، کلید ثبت‌شده نمایش داده نمی‌شود.</p>
            <?php if (get_option(GFCV_Kavenegar::MIGRATION_KEY)==='conflict' && GFCV_Kavenegar::get_key()==='') : ?>
                <div class="notice notice-warning inline"><p>در نسخه‌های قبلی چند کلید متفاوت وجود داشته است. لطفاً کلید صحیح سراسری را وارد کنید؛ هیچ‌یک خودکار انتخاب نشده است.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['api_error'])) : ?>
                <div class="notice notice-error inline"><p>ساختار کلید واردشده معتبر نیست. کلید API کاوه‌نگار را بررسی کنید.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['api_updated'])) : ?>
                <div class="notice notice-success inline"><p>تنظیمات سراسری کاوه‌نگار ذخیره شد.</p></div>
            <?php endif; ?>
            <p class="gfcv-secret-state">
                <span class="dashicons <?php echo GFCV_Kavenegar::get_key() !== '' ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
                <?php echo GFCV_Kavenegar::get_key() !== '' ? 'کلید API تنظیم شده است.' : 'هنوز کلید API ثبت نشده است.'; ?>
            </p>
            <form method="post">
                <?php wp_nonce_field('gfcv_save_view','gfcv_nonce'); ?>
                <input type="hidden" name="gfcv_action" value="save_kavenegar_settings">
                <label for="gfcv_kavenegar_key"><strong>کلید جدید API کاوه‌نگار</strong></label>
                <div class="gfcv-secret-row">
                    <input type="password" id="gfcv_kavenegar_key" name="gfcv_kavenegar_key"
                           autocomplete="new-password" placeholder="کلید جدید را برای ثبت یا جایگزینی وارد کنید">
                    <button type="submit" class="button button-primary">ذخیره تنظیمات پیامک</button>
                </div>
                <p class="description">خالی‌گذاشتن این فیلد باعث حفظ کلید قبلی می‌شود.</p>
                <label><input type="checkbox" name="gfcv_clear_kavenegar_key" value="1"> حذف کلید فعلی (ارسال پیامک و دریافت گزارش را غیرفعال می‌کند)</label>
            </form>
        </div>
    </details>

    <details class="gfcv-font-accordion" <?php echo isset($_GET['font_error']) || isset($_GET['font_updated']) ? 'open' : ''; ?>>
        <summary><span class="dashicons dashicons-editor-textcolor" aria-hidden="true"></span> تنظیمات فونت سفارشی گزارش‌ها و چاپ</summary>
    <div class="gfcv-admin-form gfcv-font-settings">
        <h2>فونت سفارشی</h2>
        <p class="description">
            برای گزارش‌های چاپی و PDF، فایل‌های فونت دلخواه خود را با دو وزن «عادی» و «Bold» معرفی کنید.
            هر فونت فارسی یا لاتین که مجوز استفاده از آن را دارید قابل استفاده است. فرمت WOFF2 ترجیح دارد.
            آدرس فایل‌های فونت بهتر است روی همان دامنه وردپرس باشد.
        </p>
        <?php if (isset($_GET['font_updated'])) : ?>
            <div class="notice notice-success inline"><p>تنظیمات فونت سفارشی ذخیره شد.</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['font_error'])) : ?>
            <div class="notice notice-error inline"><p>برای هر فایل فونت، آدرس معتبر HTTP/HTTPS با پسوند WOFF2، WOFF، TTF یا OTF وارد کنید.</p></div>
        <?php endif; ?>
        <form method="post" action="">
            <?php wp_nonce_field('gfcv_save_view', 'gfcv_nonce'); ?>
            <input type="hidden" name="gfcv_action" value="save_print_font">
            <label for="gfcv_print_font_regular_url" style="display:block;font-weight:700;margin:12px 0 6px">
                فایل فونت عادی (Regular / 400)
            </label>
            <input type="url" dir="ltr" id="gfcv_print_font_regular_url" name="gfcv_print_font_regular_url"
                value="<?php echo esc_attr(get_option('gfcv_print_font_regular_url', get_option('gfcv_print_font_url', ''))); ?>"
                placeholder="https://example.com/wp-content/uploads/fonts/Custom-Regular.woff2"
                class="large-text" style="max-width:730px;width:100%">
            <label for="gfcv_print_font_bold_url" style="display:block;font-weight:700;margin:12px 0 6px">
                فایل فونت بولد (Bold / 700)
            </label>
            <input type="url" dir="ltr" id="gfcv_print_font_bold_url" name="gfcv_print_font_bold_url"
                value="<?php echo esc_attr(get_option('gfcv_print_font_bold_url', '')); ?>"
                placeholder="https://example.com/wp-content/uploads/fonts/Custom-Bold.woff2"
                class="large-text" style="max-width:730px;width:100%">
            <p class="description" style="margin-top:9px">
                عنوان فیلدها با وزن عادی و مقادیر با وزن Bold چاپ می‌شوند.
                در گزینه‌های چندانتخابی، فقط گزینه‌های انتخاب‌شده Bold خواهند بود.
                اگر فایل Bold وارد نشود، مرورگر ممکن است وزن بولد را به‌صورت مصنوعی تولید کند.
            </p>
            <p><button type="submit" class="button button-primary">ذخیره فونت سفارشی</button></p>
        </form>
    </div>

    </details>

    <div class="gfcv-dashboard-feedback" id="gfcv-dashboard-feedback" role="status" aria-live="polite"></div>
</div>

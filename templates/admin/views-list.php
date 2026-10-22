<?php
/**
 * Admin template for displaying the list of custom views
 *
 * @package Gravity_Form_Custom_View
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap gfcv-admin-wrap" dir="rtl">
    <h1 class="wp-heading-inline"><?php _e('لیست‌های نمایشی گرویتی فرم', 'gravity-form-custom-view'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=gf_custom_view&action=add')); ?>" class="page-title-action"><?php _e('افزودن لیست جدید', 'gravity-form-custom-view'); ?></a>
    <hr class="wp-header-end">
    
    <?php if (isset($_GET['deleted'])) : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('نمای سفارشی حذف شد.', 'gravity-form-custom-view'); ?></p>
    </div>
    <?php endif; ?>
    
    <table class="wp-list-table widefat fixed striped gfcv-views-table">
        <thead>
            <tr>
                <th><?php _e('شناسه', 'gravity-form-custom-view'); ?></th>
                <th><?php _e('عنوان لیست', 'gravity-form-custom-view'); ?></th>
                <th><?php _e('فرم', 'gravity-form-custom-view'); ?></th>
                <th><?php _e('کد کوتاه', 'gravity-form-custom-view'); ?></th>
                <th><?php _e('تاریخ ایجاد', 'gravity-form-custom-view'); ?></th>
                <th><?php _e('عملیات', 'gravity-form-custom-view'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($views)) : ?>
            <tr>
                <td colspan="6"><?php _e('هنوز هیچ لیستی ایجاد نشده است.', 'gravity-form-custom-view'); ?></td>
            </tr>
            <?php else : ?>
                <?php foreach ($views as $view) : ?>
                <?php 
                $form_name = '';
                foreach ($forms as $form) {
                    if ($form['id'] == $view->form_id) {
                        $form_name = $form['title'];
                        break;
                    }
                }
                ?>
                <tr>
                    <td><?php echo esc_html($view->id); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=gf_custom_view&action=edit&view_id=' . $view->id)); ?>">
                            <?php echo esc_html($view->view_title); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html($form_name); ?></td>
                    <td><code>[gf-custom-view id=<?php echo esc_html($view->id); ?>]</code></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($view->date_created))); ?></td>
                    <td>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=gf_custom_view&action=edit&view_id=' . $view->id)); ?>" class="button button-small">
                            <?php _e('ویرایش', 'gravity-form-custom-view'); ?>
                        </a>
                        <form method="post" style="display:inline;" onsubmit="return confirm('<?php _e('آیا از حذف این لیست اطمینان دارید؟', 'gravity-form-custom-view'); ?>')">
                            <?php wp_nonce_field('gfcv_save_view', 'gfcv_nonce'); ?>
                            <input type="hidden" name="gfcv_action" value="delete_view">
                            <input type="hidden" name="view_id" value="<?php echo esc_attr($view->id); ?>">
                            <button type="submit" class="button button-small button-link-delete">
                                <?php _e('حذف', 'gravity-form-custom-view'); ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

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
</div>
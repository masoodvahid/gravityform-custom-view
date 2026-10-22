<?php
/**
 * Admin template for editing a custom view
 *
 * @package Gravity_Form_Custom_View
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$is_edit = isset($view) && $view;
$title = $is_edit ? __('ویرایش نمای سفارشی', 'gravity-form-custom-view') : __('افزودن نمای سفارشی', 'gravity-form-custom-view');

// Set default values
$view_data = array(
    'id' => 0,
    'view_title' => '',
    'form_id' => '',
    'field_ids' => '',
    'admin_sms_pattern' => 'token={5}token2={entry_id}-{40}token10={1}-{24}token20=درخواست سرمایهtemplate=admin-notify',
    'user_sms_pattern' => 'token={40}token2={entry_id}token10={24}token20={1}template=fund-request',
    'admin_sms_template_text' => '',
    'user_sms_template_text' => '',
    'kavenegar_api_key' => '',
    'send_to_api' => 0,
    'send_to_details' => '',
    'details_view_html' => '',
    'user_access_ids' => '',
    'user_mobile_field_id' => '5',
    'admin_mobile_numbers' => '',
    'show_all_options' => 1,
    'respect_conditional_logic' => 1,
    'pdf_first_name_field_id' => '',
    'pdf_last_name_field_id' => ''
);

// If editing, populate with existing data
if ($is_edit) {
    foreach ($view_data as $key => $value) {
        if (isset($view->$key)) {
            $view_data[$key] = $view->$key;
        }
    }
}
?>
<div class="wrap gfcv-admin-wrap" dir="rtl">
    <h1><?php echo esc_html($title); ?></h1>
    
    <?php if (isset($_GET['updated'])) : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('تغییرات نما ذخیره شد.', 'gravity-form-custom-view'); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['created'])) : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php _e('نمای سفارشی ایجاد شد.', 'gravity-form-custom-view'); ?></p>
    </div>
    <?php endif; ?>
    
    <form method="post" action="" class="gfcv-admin-form">
        <?php wp_nonce_field('gfcv_save_view', 'gfcv_nonce'); ?>
        <input type="hidden" name="gfcv_action" value="save_view">
        <input type="hidden" name="view_id" value="<?php echo esc_attr($view_data['id']); ?>">
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="view_title"><?php _e('عنوان نمای سفارشی', 'gravity-form-custom-view'); ?></label>
                </th>
                <td>
                    <input type="text" id="view_title" name="view_title" value="<?php echo esc_attr($view_data['view_title']); ?>" class="regular-text" required>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="form_id"><?php _e('فرم گرویتی', 'gravity-form-custom-view'); ?></label>
                </th>
                <td>
                    <select id="form_id" name="form_id" required>
                        <option value=""><?php _e('انتخاب فرم', 'gravity-form-custom-view'); ?></option>
                        <?php foreach ($forms as $form) : ?>
                            <option value="<?php echo esc_attr($form['id']); ?>" <?php selected($view_data['form_id'], $form['id']); ?>>
                                <?php echo esc_html($form['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('فرمی را که رکوردهایش در این نما نمایش داده می‌شوند انتخاب کنید.', 'gravity-form-custom-view'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">نام فایل PDF</th>
                <td>
                    <p class="description">
                        الگوی عنوان هنگام «Save as PDF»:
                        <code>شناسه فرم - نام و نام خانوادگی ثبت‌کننده - عنوان فرم</code>.
                        فیلدهای نام و نام خانوادگی را از فرم انتخاب کنید.
                        می‌توانید از زیرفیلدهای فرم‌های «نام» نیز استفاده کنید.
                    </p>
                    <?php
                    $pdf_form = $view_data['form_id'] && class_exists('GFAPI')
                        ? GFAPI::get_form(absint($view_data['form_id']))
                        : false;
                    $pdf_fields = array();
                    if ($pdf_form && !empty($pdf_form['fields'])) {
                        foreach ($pdf_form['fields'] as $pdf_field) {
                            if (!empty($pdf_field->displayOnly)) {
                                continue;
                            }
                            $pdf_fields[] = array('id' => (string) $pdf_field->id, 'label' => (string) $pdf_field->label);
                            if (!empty($pdf_field->inputs) && is_array($pdf_field->inputs)) {
                                foreach ($pdf_field->inputs as $pdf_input) {
                                    if (isset($pdf_input['id'])) {
                                        $pdf_fields[] = array(
                                            'id' => (string) $pdf_input['id'],
                                            'label' => (string) $pdf_field->label . ' — ' . (string) ($pdf_input['label'] ?? $pdf_input['id']),
                                        );
                                    }
                                }
                            }
                        }
                    }
                    ?>
                    <div class="gfcv-picker-row" style="margin:12px 0 8px">
                        <div>
                            <label for="pdf_first_name_field_id" style="display:block;margin-bottom:5px">فیلد نام</label>
                            <select name="pdf_first_name_field_id" id="pdf_first_name_field_id">
                                <option value="">انتخاب فیلد نام</option>
                                <?php foreach ($pdf_fields as $pdf_field_option) : ?>
                                <option value="<?php echo esc_attr($pdf_field_option['id']); ?>" <?php selected((string)$view_data['pdf_first_name_field_id'], $pdf_field_option['id']); ?>>
                                    <?php echo esc_html($pdf_field_option['id'] . ' — ' . $pdf_field_option['label']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="pdf_last_name_field_id" style="display:block;margin-bottom:5px">فیلد نام خانوادگی</label>
                            <select name="pdf_last_name_field_id" id="pdf_last_name_field_id">
                                <option value="">انتخاب فیلد نام خانوادگی</option>
                                <?php foreach ($pdf_fields as $pdf_field_option) : ?>
                                <option value="<?php echo esc_attr($pdf_field_option['id']); ?>" <?php selected((string)$view_data['pdf_last_name_field_id'], $pdf_field_option['id']); ?>>
                                    <?php echo esc_html($pdf_field_option['id'] . ' — ' . $pdf_field_option['label']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <p class="description">اگر یک فیلد «نام و نام خانوادگی» داری، فقط فیلد نام را انتخاب کن؛ فیلد دوم اختیاری است.</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="field_ids"><?php _e('فیلدهای جدول رکوردها', 'gravity-form-custom-view'); ?></label>
                </th>
                <td>
                    <input type="hidden" id="field_ids" name="field_ids" value="<?php echo esc_attr($view_data['field_ids']); ?>">
                    <div class="gfcv-selector">
                        <label for="gfcv_field_picker">انتخاب فیلد برای جدول</label>
                        <div class="gfcv-picker-row">
                            <select id="gfcv_field_picker"><option value="">یک فیلد انتخاب کنید...</option></select>
                            <button type="button" class="button button-secondary" id="gfcv_add_field">افزودن به جدول</button>
                        </div>
                        <div id="gfcv_selected_fields" class="gfcv-selected-fields"></div>
                    </div>
                    <div id="available_fields" class="gfcv-field-browser">
                        <strong>شناسه فیلدهای فرم</strong>
                        <p class="description">برای افزودن سریع، روی هر فیلد کلیک کنید.</p>
                        <div id="field_list" class="gfcv-field-grid">
                            <?php
                            $selected_form = $view_data['form_id'] ? GFAPI::get_form($view_data['form_id']) : null;
                            if ($selected_form && !empty($selected_form['fields'])) :
                                foreach ($selected_form['fields'] as $field) :
                                    if (!$field->displayOnly) :
                            ?>
                            <button type="button" class="field-item" data-field-id="<?php echo esc_attr($field->id); ?>">
                                <code><?php echo esc_html($field->id); ?></code><span><?php echo esc_html($field->label); ?></span>
                            </button>
                            <?php endif; endforeach; else: ?>
                                <p>برای مشاهده فیلدها ابتدا فرم را انتخاب کنید.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
            
            <tr><th scope="row">تنظیمات نمایش پاسخ‌ها</th><td>
                <label class="gfcv-setting"><input type="checkbox" name="show_all_options" value="1" <?php checked($view_data['show_all_options'], 1); ?>> نمایش تمام گزینه‌های سؤالات چندگزینه‌ای</label>
                <p class="description">وقتی غیرفعال باشد، فقط پاسخ‌های انتخاب‌شده در نمایش جزئیات می‌آیند.</p>
                <label class="gfcv-setting"><input type="checkbox" name="respect_conditional_logic" value="1" <?php checked($view_data['respect_conditional_logic'], 1); ?>> نمایش فیلدهای شرطی فقط در صورت برقراری شرط</label>
                <p class="description">این گزینه برای قالب‌های جزئیات دارای data-gf-field کار می‌کند.</p>
            </td></tr>
            <tr class="gfcv-sms-settings-row">
                <th scope="row">تنظیمات پیامک‌های الگو</th>
                <td>
                    <?php $sms_builder_type = 'admin'; include GFCV_PLUGIN_PATH . 'templates/admin/sms-builder.php'; ?>
                    <?php $sms_builder_type = 'user'; include GFCV_PLUGIN_PATH . 'templates/admin/sms-builder.php'; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="kavenegar_api_key"><?php _e('کلید API کاوه‌نگار', 'gravity-form-custom-view'); ?></label>
                </th>
                <td>
                    <input type="text" id="kavenegar_api_key" name="kavenegar_api_key" value="<?php echo esc_attr($view_data['kavenegar_api_key']); ?>" class="regular-text">
                    <p class="description"><?php _e('کلید API حساب کاوه‌نگار را وارد کنید.', 'gravity-form-custom-view'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="send_to_api"><?php _e('ارسال به API', 'gravity-form-custom-view'); ?></label>
                </th>
                <td>
                    <input type="checkbox" id="send_to_api" name="send_to_api" value="1" <?php checked($view_data['send_to_api'], 1); ?>>
                    <label for="send_to_api"><?php _e('فعال‌سازی ارتباط با API', 'gravity-form-custom-view'); ?></label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="send_to_details"><?php _e('تنظیمات ارسال به API', 'gravity-form-custom-view'); ?></label>
                </th>
                <td>
                    <textarea id="send_to_details" name="send_to_details" rows="8" class="large-text code"><?php echo esc_textarea($view_data['send_to_details']); ?></textarea>
                    <p class="description"><?php _e('جزئیات درخواست API را وارد کنید. برای داده‌ها از شناسه فیلد مانند {1} استفاده کنید.', 'gravity-form-custom-view'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="details_view_html"><?php _e('قالب HTML نمایش جزئیات', 'gravity-form-custom-view'); ?></label>
                </th>
                <td>
                    <textarea id="details_view_html" name="details_view_html" rows="10" class="large-text code"><?php echo esc_textarea($view_data['details_view_html']); ?></textarea>
                    <p class="description"><?php _e('ساختار HTML نمایش جزئیات را وارد کنید. از {field_id} برای مقادیر فرم استفاده کنید.', 'gravity-form-custom-view'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="user_access_ids"><?php _e('شناسه کاربران مجاز', 'gravity-form-custom-view'); ?></label>
                </th>
                <td>
                    <input type="text" id="user_access_ids" name="user_access_ids" value="<?php echo esc_attr($view_data['user_access_ids']); ?>" class="regular-text">
                    <p class="description"><?php _e('شناسه کاربران مجاز را با ویرگول جدا کنید؛ خالی بودن یعنی عدم اعمال محدودیت کاربران.', 'gravity-form-custom-view'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="user_mobile_field_id"><?php _e('شناسه فیلد موبایل کاربر', 'gravity-form-custom-view'); ?></label>
                </th>
                <td>
                    <input type="text" id="user_mobile_field_id" name="user_mobile_field_id" value="<?php echo esc_attr(isset($view_data['user_mobile_field_id']) ? $view_data['user_mobile_field_id'] : ''); ?>" class="regular-text">
                    <p class="description"><?php _e('شناسه فیلدی که شماره موبایل کاربر در آن ذخیره شده است (مانند ۵).', 'gravity-form-custom-view'); ?></p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="admin_mobile_numbers"><?php _e('شماره‌های موبایل مدیران', 'gravity-form-custom-view'); ?></label>
                </th>
                <td>
                    <input type="text" id="admin_mobile_numbers" name="admin_mobile_numbers" value="<?php echo esc_attr(isset($view_data['admin_mobile_numbers']) ? $view_data['admin_mobile_numbers'] : ''); ?>" class="regular-text">
                    <p class="description"><?php _e('شماره‌های مدیران را با ویرگول جدا کنید.', 'gravity-form-custom-view'); ?></p>
                </td>
            </tr>

        </table>
        
        <?php if ($is_edit) : ?>
            <h2><?php _e('کد کوتاه', 'gravity-form-custom-view'); ?></h2>
            <p><?php _e('برای نمایش لیست در سایت از کد کوتاه زیر استفاده کنید:', 'gravity-form-custom-view'); ?></p>
            <p><code>[gf-custom-view id=<?php echo esc_html($view_data['id']); ?>]</code></p>
        <?php endif; ?>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo $is_edit ? esc_attr__('ذخیره تغییرات نمای سفارشی', 'gravity-form-custom-view') : esc_attr__('ایجاد نمای سفارشی', 'gravity-form-custom-view'); ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=gf_custom_view')); ?>" class="button"><?php _e('انصراف', 'gravity-form-custom-view'); ?></a>
        </p>
    </form>
</div>


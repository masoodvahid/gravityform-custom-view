<?php
/** SMS pattern builder — one instance each for administrator and user. */
if (!defined('ABSPATH')) { exit; }
$type = ($sms_builder_type === 'admin') ? 'admin' : 'user';
$title = ($type === 'admin') ? 'پیامک مدیر' : 'پیامک کاربر';
$pattern_key = $type . '_sms_pattern';
$body_key = $type . '_sms_template_text';
$sample_form = !empty($view_data['form_id']) && class_exists('GFAPI')
    ? GFAPI::get_form((int)$view_data['form_id']) : false;
$sms_fields = array();
if ($sample_form && !empty($sample_form['fields'])) {
    foreach ($sample_form['fields'] as $field) {
        if (!empty($field->displayOnly)) { continue; }
        $sms_fields[] = array('id'=>(string)$field->id,'label'=>(string)$field->label);
        if (!empty($field->inputs) && is_array($field->inputs)) {
            foreach ($field->inputs as $input) {
                if (!empty($input['id'])) {
                    $sms_fields[] = array(
                        'id'=>(string)$input['id'],
                        'label'=>(string)$field->label . ' — ' . (string)($input['label'] ?? $input['id'])
                    );
                }
            }
        }
    }
}
$slots = array('token','token2','token3','token10','token20');
?>
<div class="gfcv-sms-builder" data-sms-type="<?php echo esc_attr($type); ?>">
    <div class="gfcv-sms-builder-header">
        <h3><?php echo esc_html($title); ?></h3>
        <p>نام پترن تأییدشده در کاوه‌نگار را وارد کنید و برای متغیرهای موردنیاز، مقدار مشخص کنید.</p>
    </div>
    <div class="gfcv-sms-builder-grid">
        <div>
            <label class="gfcv-sms-label" for="gfcv_<?php echo esc_attr($type); ?>_template">نام پترن در کاوه‌نگار</label>
            <input id="gfcv_<?php echo esc_attr($type); ?>_template" class="gfcv-sms-name" dir="ltr" type="text"
                   placeholder="my-template" autocomplete="off">
        </div>
        <div>
            <label class="gfcv-sms-label" for="gfcv_<?php echo esc_attr($type); ?>_body">متن پترن (اختیاری، برای تشخیص متغیرها و پیش‌نمایش واقعی)</label>
            <textarea id="gfcv_<?php echo esc_attr($type); ?>_body" name="<?php echo esc_attr($body_key); ?>"
                      rows="3" class="large-text gfcv-sms-body"
                      placeholder="سلام %token، نتیجه درخواست شما: %token2"><?php echo esc_textarea($view_data[$body_key] ?? ''); ?></textarea>
            <span class="description">متن را از پنل کاوه‌نگار کپی کنید. فقط در سایت ذخیره می‌شود و به سرویس دیگری ارسال نمی‌شود.</span>
        </div>
    </div>
    <div class="gfcv-sms-token-heading">
        <strong>متغیرهای پترن</strong>
        <span class="description">اگر متن پترن وارد شده باشد، متغیرها خودکار فعال می‌شوند؛ در غیر این‌صورت از فهرست زیر انتخاب کنید.</span>
    </div>
    <div class="gfcv-sms-tokens">
        <?php foreach ($slots as $slot) : ?>
            <div class="gfcv-sms-token" data-token="<?php echo esc_attr($slot); ?>">
                <label class="gfcv-sms-token-enable"><input type="checkbox" class="gfcv-sms-enabled">
                    <code dir="ltr"><?php echo esc_html('%' . $slot); ?></code>
                </label>
                <select class="gfcv-sms-source" aria-label="نوع مقدار <?php echo esc_attr($slot); ?>">
                    <option value="field">فیلد فرم</option>
                    <option value="entry">شناسه رکورد</option>
                    <option value="constant">متن یا مقدار ثابت</option>
                    <option value="expression">ترکیب چند مقدار (پیشرفته)</option>
                </select>
                <select class="gfcv-sms-field" aria-label="شناسه فیلد <?php echo esc_attr($slot); ?>">
                    <option value="">انتخاب فیلد فرم...</option>
                    <?php foreach ($sms_fields as $sms_field) : ?>
                        <option value="<?php echo esc_attr($sms_field['id']); ?>"><?php echo esc_html($sms_field['id'] . ' — ' . $sms_field['label']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" class="gfcv-sms-value" dir="auto" placeholder="مقدار ثابت یا ترکیب مثل {entry_id}-{40}" aria-label="مقدار <?php echo esc_attr($slot); ?>">
            </div>
        <?php endforeach; ?>
    </div>
    <div class="gfcv-sms-preview-wrap">
        <div class="gfcv-sms-preview-heading"><strong>پیش‌نمایش زنده پیامک</strong><span class="description gfcv-sms-sample-source"></span></div>
        <div class="gfcv-sms-preview" role="status" aria-live="polite">برای پیش‌نمایش، نام پترن و حداقل یک متغیر را تعیین کنید.</div>
        <p class="description">اگر متن پترن را وارد نکرده‌اید، این قسمت فقط مقادیر جایگزین‌شده را نشان می‌دهد؛ متن واقعی ارسالی توسط کاوه‌نگار تعیین می‌شود.</p>
    </div>
    <div class="gfcv-sms-test-wrap">
        <label class="gfcv-sms-label">شماره موبایل دریافت‌کننده پیامک تستی</label>
        <div class="gfcv-sms-test-row">
            <input type="tel" class="gfcv-sms-test-mobile" dir="ltr" inputmode="tel" placeholder="09123456789" autocomplete="off">
            <button type="button" class="button button-secondary gfcv-sms-test-button">بررسی و ارسال آزمایشی</button>
        </div>
        <p class="description">ابتدا تغییرات را ذخیره کنید. قبل از ارسال واقعی، نام پترن، شماره و مقادیر پیام برای تأیید نمایش داده می‌شوند. ارسال آزمایشی ممکن است هزینه داشته باشد.</p>
        <div class="gfcv-sms-test-status" role="status" aria-live="polite"></div>
    </div>
    <details class="gfcv-sms-advanced">
        <summary>نمایش یا ویرایش ساختار خام (LTR) — برای سازگاری با تنظیمات قدیمی</summary>
        <label class="gfcv-sms-label" for="<?php echo esc_attr($pattern_key); ?>">ساختار <?php echo esc_html($title); ?></label>
        <textarea name="<?php echo esc_attr($pattern_key); ?>" id="<?php echo esc_attr($pattern_key); ?>"
                  dir="ltr" rows="4" class="large-text gfcv-sms-raw"><?php echo esc_textarea($view_data[$pattern_key] ?? ''); ?></textarea>
        <p class="description">با تغییر تنظیمات سازنده، این کد به‌طور خودکار به‌روزرسانی می‌شود.</p>
    </details>
</div>

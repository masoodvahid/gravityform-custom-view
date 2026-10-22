/* Gravity Forms Custom View: visual Kavenegar SMS token mapper. */
(function($) {
'use strict';
$(function() {
    var $builders = $('.gfcv-sms-builder');
    if (!$builders.length) { return; }
    var sample = {entry_id:0,values:{}};
    var order = ['token','token2','token3','token10','token20'];
    var nonce = window.gfcvAdminData && window.gfcvAdminData.builderNonce;

    function parseRaw(raw) {
        var found = [], params = {};
        var re = /(token(?:2|3|10|20)?|template)=/g, match;
        while ((match = re.exec(raw || '')) !== null) {
            found.push({name: match[1], begin: match.index, start: re.lastIndex});
        }
        found.forEach(function(part,i) {
            var until = i+1 < found.length ? found[i+1].begin : raw.length;
            params[part.name] = raw.slice(part.start,until).trim();
        });
        return params;
    }
    function resolveToken(value) {
        return String(value || '').replace(/\{(entry_id|\d+(?:\.\d+)?)\}/g, function(full,id) {
            if (id === 'entry_id') { return String(sample.entry_id || 'نمونه'); }
            return sample.values && sample.values[id] ? String(sample.values[id]) : '«مقدار آزمایشی»';
        });
    }
    function tokenKeys(body) {
        var keys = [], re = /%(token(?:10|20|2|3)?)(?=%|\b)/g, match;
        while ((match = re.exec(body || '')) !== null) {
            if (keys.indexOf(match[1]) === -1) { keys.push(match[1]); }
        }
        return keys;
    }
    function makeRaw($builder) {
        var params=[], template=String($builder.find('.gfcv-sms-name').val()||'').trim();
        order.forEach(function(key) {
            var $row=$builder.find('.gfcv-sms-token[data-token="'+key+'"]');
            if (!$row.find('.gfcv-sms-enabled').prop('checked')) { return; }
            var mode=$row.find('.gfcv-sms-source').val(), value='';
            if(mode==='entry') { value='{entry_id}'; }
            else if(mode==='field') {
                var id=$row.find('.gfcv-sms-field').val();
                value=id ? '{'+id+'}' : '';
            } else { value=$row.find('.gfcv-sms-value').val() || ''; }
            params.push(key+'='+value);
        });
        if(template) { params.push('template='+template); }
        return params.join('');
    }
    function updateRows($builder) {
        $builder.find('.gfcv-sms-token').each(function() {
            var $row=$(this), enabled=$row.find('.gfcv-sms-enabled').prop('checked');
            var type=$row.find('.gfcv-sms-source').val();
            $row.toggleClass('is-disabled',!enabled);
            $row.find('.gfcv-sms-field').toggle(enabled && type==='field').prop('disabled',!enabled||type!=='field');
            $row.find('.gfcv-sms-value').toggle(enabled && (type==='constant'||type==='expression'))
                .prop('disabled',!enabled||(type!=='constant'&&type!=='expression'));
            $row.find('.gfcv-sms-source').prop('disabled',!enabled);
        });
    }
    function showPreview($builder) {
        var pattern = parseRaw($builder.find('.gfcv-sms-raw').val());
        var body = $builder.find('.gfcv-sms-body').val() || '';
        var params={};
        order.forEach(function(key) {
            if(pattern[key] !== undefined) { params[key] = resolveToken(pattern[key]); }
        });
        var text;
        if(body.trim()) {
            text=body.replace(/%(token(?:10|20|2|3)?)(?=%|\b)/g,function(m,key) {
                return params[key] !== undefined ? params[key] : '['+key+' تنظیم نشده]';
            });
        } else {
            var lines=['پترن: '+(pattern.template||'تنظیم نشده')];
            order.forEach(function(key) { if (params[key] !== undefined) { lines.push(key+' = '+params[key]); } });
            text=lines.join('\n');
        }
        $builder.find('.gfcv-sms-preview').text(text);
        $builder.find('.gfcv-sms-sample-source').text(sample.entry_id
            ? 'نمونه از رکورد '+sample.entry_id : 'پیش‌نمایش با مقادیر نمونه');
    }
    function update($builder) {
        updateRows($builder);
        $builder.find('.gfcv-sms-raw').val(makeRaw($builder));
        showPreview($builder);
    }
    function fromRaw($builder) {
        var params=parseRaw($builder.find('.gfcv-sms-raw').val()||'');
        $builder.find('.gfcv-sms-name').val(params.template||'');
        order.forEach(function(key) {
            var $row=$builder.find('.gfcv-sms-token[data-token="'+key+'"]');
            var active=Object.prototype.hasOwnProperty.call(params,key), val=params[key]||'';
            $row.find('.gfcv-sms-enabled').prop('checked',active);
            if (/^\{\d+(?:\.\d+)?\}$/.test(val)) {
                $row.find('.gfcv-sms-source').val('field');
                $row.find('.gfcv-sms-field').val(val.slice(1,-1));
            } else if(val==='{entry_id}') {
                $row.find('.gfcv-sms-source').val('entry');
            } else {
                $row.find('.gfcv-sms-source').val(/[{}]/.test(val) ? 'expression':'constant');
                $row.find('.gfcv-sms-value').val(val);
            }
        });
        updateRows($builder);showPreview($builder);
    }
    function updateOptions(fields) {
        $builders.each(function() {
            var $b=$(this);
            $b.find('.gfcv-sms-field').each(function() {
                var $sel=$(this), last=$sel.val();
                $sel.empty().append($('<option/>').val('').text('انتخاب فیلد فرم...'));
                fields.forEach(function(item) {
                    $sel.append($('<option/>').val(String(item.id)).text(item.id+' — '+item.label));
                });
                $sel.val(last);
            });
            fromRaw($b);
        });
    }
    function loadSample() {
        var formId=$('#form_id').val();
        sample={entry_id:0,values:{}};
        if(!formId||!nonce) { $builders.each(function(){showPreview($(this));});return; }
        $.post(ajaxurl,{action:'gfcv_sms_builder_sample',form_id:formId,nonce:nonce})
         .done(function(resp){
             if ($('#form_id').val() !== formId) { return; }
             if(resp&&resp.success) { sample=resp.data; }
             $builders.each(function(){showPreview($(this));});
         }).fail(function(){ $builders.each(function(){showPreview($(this));}); });
    }

    $builders.each(function() {
        var $b=$(this);
        fromRaw($b);
        $b.on('input change','.gfcv-sms-name,.gfcv-sms-source,.gfcv-sms-enabled,.gfcv-sms-field,.gfcv-sms-value',function() {
            update($b);
        });
        $b.on('input','.gfcv-sms-body',function() {
            var found=tokenKeys($(this).val());
            if(found.length) {
                order.forEach(function(key){
                    if(found.indexOf(key)!==-1){
                        $b.find('.gfcv-sms-token[data-token="'+key+'"] .gfcv-sms-enabled').prop('checked',true);
                    }
                });
            }
            update($b);
        });
        $b.on('input','.gfcv-sms-raw',function() { fromRaw($b); });
        $b.find('.gfcv-sms-test-button').on('click',function(){
            var $button=$(this), $status=$b.find('.gfcv-sms-test-status');
            var mobile=$b.find('.gfcv-sms-test-mobile').val().trim();
            if(!mobile) { $status.text('ابتدا شماره موبایل تستی را وارد کنید.');return; }
            if(!nonce) { $status.text('کلید امنیتی تست موجود نیست.');return; }
            $status.text('در حال آماده‌سازی پیش‌نمایش قابل تأیید...');$button.prop('disabled',true);
            $.post(ajaxurl,{
                action:'gfcv_sms_builder_preview',nonce:nonce,
                view_id:$('input[name=view_id]').val(),form_id:$('#form_id').val(),
                type:$b.data('sms-type'),pattern:$b.find('.gfcv-sms-raw').val(),
                template_body:$b.find('.gfcv-sms-body').val(),mobile:mobile
            }).done(function(resp){
                if(!resp||!resp.success) {
                    $status.text(resp&&resp.data&&resp.data.message||'خطا در پیش‌نمایش پیامک.');return;
                }
                var data=resp.data, review='شماره: '+mobile+'\n'+data.source+'\n\n'+data.preview+
                    '\n\nمقادیر ارسالی: '+JSON.stringify(data.params,null,2);
                $status.empty().append($('<pre class="gfcv-sms-preview"/>').text(review));
                if(!data.can_send) {
                    $status.append($('<p/>').text(data.message||'برای ارسال واقعی ابتدا نما را ذخیره کنید.'));
                    return;
                }
                var $send=$('<button type="button" class="button button-primary">تأیید نهایی و ارسال واقعی</button>');
                $status.append($send);
                $send.on('click',function(){
                    if(!window.confirm('آیا ارسال واقعی پترن '+data.params.template+' به '+mobile+
                        ' با مقادیر پیش‌نمایش داده‌شده را تأیید می‌کنید؟ این ارسال می‌تواند هزینه داشته باشد.')){return;}
                    $send.prop('disabled',true);$status.append($('<p/>').text('در حال ارسال...'));
                    $.post(ajaxurl,{action:'gfcv_sms_builder_send',nonce:nonce,ticket:data.ticket})
                     .done(function(res){
                        $status.append($('<p/>').text(res&&res.data&&res.data.message||'نتیجه ارسال دریافت نشد.'));
                     }).fail(function(xhr){
                        $status.append($('<p/>').text(xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message||'خطای شبکه در ارسال.'));
                     });
                });
            }).fail(function(xhr){
                $status.text(xhr.responseJSON&&xhr.responseJSON.data&&xhr.responseJSON.data.message||'خطا در ارتباط با سرور.');
            }).always(function(){ $button.prop('disabled',false); });
        });
    });
    // Populate compound Gravity Forms input IDs after selecting a form.
    $(document).on('gfcv:form-fields-loaded',function(event, fields){
        updateOptions(fields||[]);loadSample();
    });
    $('#form_id').on('change',function(){
        sample={entry_id:0,values:{}};
        loadSample();
    });
    // Initial markup contains only simple field IDs and will be replaced if
    // the form changes; on edit, the nested inputs are also passed from PHP.
    loadSample();
});
})(jQuery);

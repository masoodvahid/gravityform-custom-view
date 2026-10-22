(function($){
'use strict';
$(function(){
 const $input=$('#field_ids'), $select=$('#gfcv_field_picker'), $list=$('#gfcv_selected_fields');
 if(!$input.length)return;
 let names={}, ids=[];
 function render(){
   $input.val(ids.join(','));
   $list.empty();
   ids.forEach(function(id){
     const item=$('<span class="gfcv-selected-chip"/>').text((names[id]||'فیلد')+' ('+id+') ');
     $('<button type="button" class="gfcv-remove-chip" aria-label="حذف فیلد">×</button>').on('click',function(){ids=ids.filter(x=>x!==id);render();}).appendTo(item);
     $list.append(item);
   });
   $select.find('option').each(function(){if(this.value)$(this).prop('disabled',ids.includes(this.value));});
   $('#field_list .field-item').each(function(){$(this).toggleClass('is-selected',ids.includes(String($(this).data('field-id'))));});
 }
 function add(id){id=String(id||'');if(!/^\d+$/.test(id)||ids.includes(id))return;ids.push(id);render();}
 function fillPdfNameFields(options){
   ['#pdf_first_name_field_id','#pdf_last_name_field_id'].forEach(function(selector){
     const $field=$(selector);
     if(!$field.length)return;
     const placeholder=selector.indexOf('first')!==-1?'انتخاب فیلد نام':'انتخاب فیلد نام خانوادگی';
     $field.empty().append($('<option/>').val('').text(placeholder));
     (options||[]).forEach(function(option){
       $field.append($('<option/>').val(String(option.id)).text(option.id+' — '+option.label));
     });
     $field.val('');
   });
 }
 function readFields(){
   names={};$select.empty().append($('<option value="">یک فیلد انتخاب کنید...</option>'));
   $('#field_list .field-item').each(function(){
     const id=String($(this).data('field-id')),name=$(this).find('span').text();
     names[id]=name;
     $select.append($('<option/>').val(id).text(id+' — '+name));
   });
   render();
 }
 ids=($input.val()||'').split(',').map(v=>v.trim()).filter(v=>/^\d+$/.test(v));
 readFields();
 $('#gfcv_add_field').on('click',function(){add($select.val());$select.val('');});
 $(document).on('click','#field_list .field-item',function(){add($(this).data('field-id'));});
 $('#form_id').on('change',function(){
   const formId=$(this).val();ids=[];render();fillPdfNameFields([]);
   if(!formId){$('#field_list').html('<p>ابتدا فرم را انتخاب کنید.</p>');readFields();return;}
   $('#field_list').html('<p>در حال بارگذاری فیلدها...</p>');
   $.post(ajaxurl,{action:'gfcv_get_form_fields',form_id:formId,nonce:$('#gfcv_nonce').val()},function(resp){
      if(resp&&resp.success){$('#field_list').html(resp.data.html);readFields();fillPdfNameFields(resp.data.pdf_fields||resp.data.fields||[]);$(document).trigger('gfcv:form-fields-loaded',[resp.data.pdf_fields||resp.data.fields||[]]);}
      else $('#field_list').text('خطا در دریافت فیلدهای فرم');
   }).fail(function(){$('#field_list').text('خطا در ارتباط با سرور');});
 });
 if(window.wp&&wp.codeEditor){
   ['#send_to_details','#details_view_html'].forEach(function(sel){if($(sel).length)wp.codeEditor.initialize($(sel));});
 }
 const $test=$('#gfcv_test_sms'),$result=$('#gfcv_sms_test_result');
 $test.on('click',function(){
   const viewId=$('input[name=view_id]').val(),nonce=window.gfcvAdminData&&gfcvAdminData.smsNonce;
   if(!nonce){$result.text('کلید امنیتی تست موجود نیست.');return;}
   $test.prop('disabled',true);$result.text('در حال دریافت پیش‌نمایش...');
   $.post(ajaxurl,{action:'gfcv_preview_sms',view_id:viewId,nonce:nonce}).done(function(resp){
     if(!resp.success){$result.text(resp.data&&resp.data.message||'خطا در پیش‌نمایش');return;}
     const data=resp.data, details=JSON.stringify(data.params,null,2);
     $result.empty().append($('<p/>').text('رکورد نمونه: '+data.entry_id+' | گیرندگان: '+data.recipients.join('، ')));
     $result.append($('<pre class="gfcv-sms-preview"/>').text(details));
     const $send=$('<button type="button" class="button button-primary">تأیید و ارسال پیامک آزمایشی</button>');
     $result.append($send);
     $send.on('click',function(){
       if(!window.confirm('پیامک واقعی به '+data.recipients.length+' شماره ارسال می‌شود و ممکن است هزینه داشته باشد. ادامه می‌دهید؟'))return;
       $send.prop('disabled',true);$result.append($('<p/>').text('در حال ارسال...'));
       $.post(ajaxurl,{action:'gfcv_test_sms',view_id:viewId,nonce:nonce,fingerprint:data.fingerprint}).done(function(res){
         if(!res.success){$result.append($('<p/>').text(res.data&&res.data.message||'ارسال ناموفق'));return;}
         res.data.results.forEach(function(item){$result.append($('<p/>').text(item.number+' : '+(item.success?'ارسال شد':'ناموفق')+' - '+item.message));});
       }).fail(function(){$result.append($('<p/>').text('خطای شبکه در زمان ارسال'));});
     });
   }).fail(function(){$result.text('خطا در ارتباط با سرور');}).always(function(){$test.prop('disabled',false);});
 });
});
})(jQuery);

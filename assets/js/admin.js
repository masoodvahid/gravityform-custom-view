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
 function fillFormFieldSelectors(options, keepExisting){
   const selectors=[
     {selector:'#pdf_first_name_field_id',placeholder:'انتخاب فیلد نام'},
     {selector:'#pdf_last_name_field_id',placeholder:'انتخاب فیلد نام خانوادگی'},
     {selector:'#user_mobile_field_id',placeholder:'انتخاب فیلد شماره همراه'},
     {selector:'#fallback_mobile_field_id',placeholder:'بدون فیلد جایگزین'}
   ];
   selectors.forEach(function(cfg){
     const $field=$(cfg.selector);
     if(!$field.length)return;
     const current=keepExisting?$field.val():'';
     $field.empty().append($('<option/>').val('').text(cfg.placeholder));
     (options||[]).forEach(function(option){
       $field.append($('<option/>').val(String(option.id)).text(option.id+' — '+option.label));
     });
     if(current){
       // Preserve a configured ID even if Gravity Forms has since hidden or
       // removed that input; the administrator can select another field.
       if(!$field.find('option').filter(function(){return this.value===String(current);}).length){
         $field.append($('<option/>').val(current).text(current+' — فیلد ذخیره‌شده (در فرم یافت نشد)'));
       }
       $field.val(current);
     }else{
       $field.val('');
     }
   });
 }
 // Only admins can query WordPress user profiles for saved ACL IDs.
 let userLookupTimer=null, userLookupRequest=0;
 function updateAllowedUserNames(){
   const $input=$('#user_access_ids'),$output=$('#gfcv-allowed-user-names');
   if(!$input.length||!$output.length)return;
   const raw=$input.val().trim();
   if(!raw){$output.empty();return;}
   const nonce=$('#gfcv_nonce').val();
   const requested=++userLookupRequest;
   $output.text('در حال بررسی شناسه‌های کاربران...');
   $.post(ajaxurl,{action:'gfcv_resolve_allowed_users',nonce:nonce,ids:raw})
     .done(function(resp){
       if(requested!==userLookupRequest)return;
       $output.empty();
       if(!resp||!resp.success){$output.text('دریافت مشخصات کاربران امکان‌پذیر نیست.');return;}
       (resp.data.users||[]).forEach(function(user){
         const text=String(user.id)+' — '+(user.found?user.name:'کاربر یافت نشد');
         $('<span class="gfcv-allowed-user-chip"/>')
           .toggleClass('is-missing',!user.found).text(text).appendTo($output);
       });
     })
     .fail(function(){if(requested===userLookupRequest)$output.text('خطا در دریافت نام کاربران.');});
 }
 $('#user_access_ids').on('input',function(){
   clearTimeout(userLookupTimer);
   userLookupTimer=setTimeout(updateAllowedUserNames,350);
 });
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
   const formId=$(this).val();ids=[];render();fillFormFieldSelectors([],false);
   if(!formId){$('#field_list').html('<p>ابتدا فرم را انتخاب کنید.</p>');readFields();return;}
   $('#field_list').html('<p>در حال بارگذاری فیلدها...</p>');
   $.post(ajaxurl,{action:'gfcv_get_form_fields',form_id:formId,nonce:$('#gfcv_nonce').val()},function(resp){
      if(resp&&resp.success){$('#field_list').html(resp.data.html);readFields();fillFormFieldSelectors(resp.data.pdf_fields||resp.data.fields||[],false);$(document).trigger('gfcv:form-fields-loaded',[resp.data.pdf_fields||resp.data.fields||[]]);}
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

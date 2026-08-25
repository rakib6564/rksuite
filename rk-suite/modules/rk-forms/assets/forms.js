(function () {
  'use strict';
  function ready(fn){ document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }

  function val(form, name){
    var el = form.querySelector('[name="'+name+'"]');
    if(!el) return '';
    if(el.type === 'checkbox') return el.checked ? (el.value||'1') : '';
    if(el.type === 'radio'){ var c = form.querySelector('[name="'+name+'"]:checked'); return c ? c.value : ''; }
    return el.value || '';
  }
  function test(op, cur, target){
    switch(op){
      case 'eq': return cur === target;
      case 'ne': return cur !== target;
      case 'filled': return cur !== '';
      case 'empty': return cur === '';
      case 'gt': return isFinite(cur) && isFinite(target) && parseFloat(cur) > parseFloat(target);
      case 'lt': return isFinite(cur) && isFinite(target) && parseFloat(cur) < parseFloat(target);
    }
    return true;
  }
  function applyConditions(form){
    form.querySelectorAll('[data-rk-show-field]').forEach(function(row){
      var f = row.getAttribute('data-rk-show-field');
      var op = row.getAttribute('data-rk-show-op') || 'eq';
      var target = row.getAttribute('data-rk-show-val') || '';
      var show = test(op, String(val(form, f)).trim(), String(target).trim());
      row.style.display = show ? '' : 'none';
      row.querySelectorAll('input,select,textarea').forEach(function(i){ i.disabled = !show; });
    });
  }

  function submit(form){
    var id = form.getAttribute('data-rk-form');
    var nonce = form.getAttribute('data-rk-nonce');
    var btn = form.querySelector('.rk-form-submit');
    var spin = form.querySelector('.rk-form-spinner');
    var msg = form.querySelector('.rk-form-msg');
    form.querySelectorAll('.rk-invalid').forEach(function(r){ r.classList.remove('rk-invalid'); });
    form.querySelectorAll('.rk-form-err').forEach(function(e){ e.remove(); });
    if (msg){ msg.hidden = true; msg.className = 'rk-form-msg'; }

    var fd = new FormData(form);
    fd.append('action', 'rk_form_submit');
    fd.append('rk_form_id', id);
    fd.append('rk_nonce', nonce);

    if (btn) btn.disabled = true;
    if (spin) spin.hidden = false;

    fetch(RKForms.ajax, { method:'POST', body: fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (btn) btn.disabled = false;
        if (spin) spin.hidden = true;
        if (res && res.success){
          if (res.data && res.data.redirect){ window.location.href = res.data.redirect; return; }
          if (msg){ msg.textContent = (res.data && res.data.message) || 'Thanks!'; msg.className = 'rk-form-msg is-ok'; msg.hidden = false; }
          form.querySelectorAll('.rk-form-fields, .rk-form-foot').forEach(function(n){ n.style.display='none'; });
        } else {
          var d = res && res.data ? res.data : {};
          if (d.fields){
            Object.keys(d.fields).forEach(function(name){
              var row = form.querySelector('[data-rk-name="'+name+'"]');
              if (row){ row.classList.add('rk-invalid'); var e=document.createElement('div'); e.className='rk-form-err'; e.textContent=d.fields[name]; row.appendChild(e); }
            });
          }
          if (msg){ msg.textContent = d.message || 'Something went wrong.'; msg.className='rk-form-msg is-err'; msg.hidden=false; }
        }
      })
      .catch(function(){
        if (btn) btn.disabled = false;
        if (spin) spin.hidden = true;
        if (msg){ msg.textContent='Network error — please try again.'; msg.className='rk-form-msg is-err'; msg.hidden=false; }
      });
  }

  ready(function(){
    document.querySelectorAll('form.rk-form').forEach(function(form){
      applyConditions(form);
      form.addEventListener('input', function(){ applyConditions(form); });
      form.addEventListener('change', function(){ applyConditions(form); });
      form.addEventListener('submit', function(e){ e.preventDefault(); if (form.checkValidity()){ submit(form); } else { form.reportValidity(); } });
    });
  });
})();

{{-- resources/views/front/surveys/embed-success.blade.php --}}
<!doctype html><html><body>
<script>
  window.parent.postMessage({ type: 'survey:saved' }, '*');
</script>
<div style="padding:12px;font:14px/1.4 system-ui">Đã lưu, bạn có thể đóng cửa sổ này.</div>
</body></html>

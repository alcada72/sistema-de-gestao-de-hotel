<script>
  window.HOTELIER_DATA = <?= json_encode(
    $dadosDashboard,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR
  ) ?>;
</script>
<script src="./assets/js/app.js"></script>
<script type="module" src="./assets/js/test.js"></script>
</body>

</html>
<script>
  window.BASE_URL = <?= json_encode(BASE_URL) ?>;
  window.DATAS = <?= json_encode($general_data) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/localizacao-hotel.js"></script>
<script src="<?= BASE_URL ?>/assets/js/navigate.js"></script>
<script src="./assets/js/script.js"></script>
<script src="./assets/js/toggleSidebar.js"></script>
<script src="./assets/js/tab_menu.js"></script>
<script>
  function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
  }
</script>
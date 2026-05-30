<?php
// inc/script.php
// ensure $basePath is defined
if (!isset($basePath)) {
    $basePath = (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false
        || strpos($_SERVER['REQUEST_URI'], '/payment/') !== false) ? '../' : './';
}
?>
    <!-- Content End (closing tags are placed by this include) -->

    <!-- Back to Top -->
    <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>
</div> <!-- end container-xxl -->

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/chart/chart.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="lib/tempusdominus/js/moment.min.js"></script>
    <script src="lib/tempusdominus/js/moment-timezone.min.js"></script>
    <script src="lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js"></script>
<!-- Template Javascript (WORLDISON's main script) -->
<script src="js/main.js"></script>

<!-- small script: hide spinner when page is loaded -->
<script>
  document.addEventListener('readystatechange', function() {
    const spinner = document.getElementById('spinner');
    if (!spinner) return;
    if (document.readyState === 'complete') {
      spinner.classList.remove('show');
    } else {
      spinner.classList.add('show');
    }
  });

  // sidebar toggle for small screens (WORLDISON uses .sidebar-toggler)
  document.addEventListener('click', function(e) {
    if (e.target.closest('.sidebar-toggler')) {
      document.querySelector('.sidebar')?.classList.toggle('collapsed');
      document.querySelector('.content')?.classList.toggle('collapsed');
    }
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.table').forEach(table => {
    new simpleDatatables.DataTable(table, {
      searchable: true,
      fixedHeight: true,
      perPage: 10
    });
  });
});
</script>
<script>
const body = document.body;
const sidebarToggle = document.getElementById("sidebarToggle");
if (sidebarToggle) {
  sidebarToggle.addEventListener("click", () => {
    body.classList.toggle("sidebar-collapsed");
  });
}
</script>
</body>
</html>

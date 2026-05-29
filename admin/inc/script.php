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
  document.addEventListener('click', function(e){
    if (e.target.closest('.sidebar-toggler')) {
      document.querySelector('.sidebar').classList.toggle('collapsed');
      document.querySelector('.content')?.classList.toggle('collapsed');
    }
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" ></script>
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

document.getElementById("sidebarToggle").addEventListener("click", () => {
    body.classList.toggle("sidebar-collapsed");
});


</script>

<script>
Quill.register('modules/imageResize', window.ImageResize);

// Toolbar configuration
const fullToolbar = [
    [{ font: [] }, { size: [] }],
    [{ header: [1,2,3,4,5,6,false] }],
    ['bold','italic','underline','strike'],
    [{ color: [] }, { background: [] }],
    [{ align: [] }],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ indent: '-1' }, { indent: '+1' }],
    ['blockquote','code-block'],
    ['link','image'],
    ['clean']
];

// Initialize course description editor
const courseQuill = new Quill('#courseDescriptionEditor', {
    theme: 'snow',
    modules: {
        toolbar: fullToolbar,
        imageResize: {}
    }
});

// Load existing content from PHP
const initialHtml = <?php echo json_encode(htmlspecialchars_decode($course['description'] ?? '')); ?>;
courseQuill.root.innerHTML = initialHtml;

// Image upload handler
function selectLocalImage(quill) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.click();

    input.onchange = () => {
        const file = input.files[0];
        if (!file) return;

        const range = quill.getSelection(true);
        const formData = new FormData();
        formData.append('image', file);

        fetch('inc/upload_quill_image.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.url) {
                    quill.insertEmbed(range.index, 'image', data.url);
                } else {
                    alert('Image upload failed.');
                    console.error(data);
                }
            })
            .catch(err => {
                alert('Image upload failed.');
                console.error(err);
            });
    };
}

// Attach image handler
courseQuill.getModule('toolbar').addHandler('image', function() {
    selectLocalImage(courseQuill);
});

// Copy Quill content to hidden textarea on form submit
document.querySelector('form[method="POST"]').addEventListener('submit', function() {
    document.getElementById('courseDescription').value = courseQuill.root.innerHTML;

    // Sync dynamic lesson editors (if any)
    document.querySelectorAll(".quill-editor").forEach(ed => {
        const target = ed.dataset.target;
        const textarea = document.querySelector(`textarea[name="${target}"]`);
        if (textarea && ed.__quillInstance) {
            textarea.value = ed.__quillInstance.root.innerHTML;
        }
    });
});
</script>


</body>
</html>

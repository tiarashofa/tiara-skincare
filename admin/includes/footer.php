    </main>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay" style="display: none;">
    <div class="spinner"></div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom Scripts -->
<script>
// Toggle sidebar on mobile
document.querySelector('.navbar-toggler').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('show');
});

// Active menu highlight
const currentPath = window.location.pathname;
document.querySelectorAll('.sidebar .nav-link').forEach(link => {
    if (link.getAttribute('href') === currentPath) {
        link.classList.add('active');
    }
});

// Show loading overlay
function showLoading() {
    document.querySelector('.loading-overlay').style.display = 'flex';
}

// Hide loading overlay
function hideLoading() {
    document.querySelector('.loading-overlay').style.display = 'none';
}

// Add loading state to forms
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        showLoading();
    });
});
</script>
</body>
</html> 
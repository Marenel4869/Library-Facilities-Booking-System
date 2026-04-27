// Auto-dismiss alerts after 4 seconds
// (Guarded so pages without Bootstrap don't error)
document.addEventListener('DOMContentLoaded', function () {
    if (!window.bootstrap || !bootstrap.Alert) return;

    setTimeout(function () {
        document.querySelectorAll('.alert').forEach(function (el) {
            var a = bootstrap.Alert.getOrCreateInstance(el);
            a.close();
        });
    }, 4000);
});
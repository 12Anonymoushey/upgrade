<!-- amo na ya code ka modal para naman sa success kag -->
<div id="customAlertOverlay" style="display: none;">
    <div class="alert-card">
        <h3 id="alertTitle">Notification</h3>
        <p id="customAlertText"></p>
        <div id="alertButtonContainer" class="alert-btn-group"></div>
    </div>
</div>

<script>
(function() {
    // Dynamic CSS Link
    const isSub = window.location.pathname.includes('/admin/') || window.location.pathname.includes('/user/');
    const cssPath = isSub ? '../css/style.css' : 'css/style.css';
    if (!document.querySelector(`link[href*="style.css"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet'; link.href = cssPath;
        document.head.appendChild(link);
    }

    const overlay = document.getElementById('customAlertOverlay');
    const text = document.getElementById('customAlertText');
    const container = document.getElementById('alertButtonContainer');

    // Show message AFTER redirect
    window.addEventListener('load', function() {
        const pendingMsg = sessionStorage.getItem('upGrade_alert');
        if (pendingMsg) {
            text.innerText = pendingMsg;
            container.innerHTML = '<button class="btn-custom-alert btn-alert-primary" id="closeAlert">OK</button>';
            overlay.style.display = 'flex';
            document.getElementById('closeAlert').onclick = () => {
                overlay.style.display = 'none';
                sessionStorage.removeItem('upGrade_alert');
            };
        }
    });

    // Capture alert and save for next page
    window.alert = function(message) {
        sessionStorage.setItem('upGrade_alert', message);
    };
})();
</script>
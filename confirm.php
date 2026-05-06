<!-- <div id="customConfirmOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center;">
    <div class="alert-card" style="background: white; padding: 20px; border-radius: 8px; text-align: center; min-width: 300px;">
        <h3 id="confirmTitle">Confirmation</h3>
        <p id="customConfirmText" style="margin: 15px 0;"></p>
        <div id="confirmButtonContainer" class="alert-btn-group" style="display: flex; gap: 10px; justify-content: center;"></div>
    </div>
</div>

<script>
(function() {
    // 1. Dynamic CSS Link (Matches alert.php logic)
    const isSub = window.location.pathname.includes('/admin/') || window.location.pathname.includes('/user/');
    const cssPath = isSub ? '../css/style.css' : 'css/style.css';
    if (!document.querySelector(`link[href*="style.css"]`)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet'; 
        link.href = cssPath;
        document.head.appendChild(link);
    }

    // 2. Setup Overlay Variables
    const overlay = document.getElementById('customConfirmOverlay');
    const text = document.getElementById('customConfirmText');
    const container = document.getElementById('confirmButtonContainer');

    // 3. Override default window.confirm
    window.confirm = function(message) {
        if (!overlay) {
            console.error("Confirm Modal HTML is missing!");
            return true; 
        }

        const trigger = window.event ? (window.event.currentTarget || window.event.target) : null;
        text.innerText = message;
        container.innerHTML = ""; 

        // Yes Button
        const yesBtn = document.createElement('button');
        yesBtn.innerText = "Yes";
        yesBtn.className = "btn-custom-alert btn-alert-danger";
        yesBtn.onclick = function() {
            overlay.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling just in case

            if (trigger) {
                // Set the success message for alert.php to pick up on the next page
                sessionStorage.setItem('upGrade_alert', 'Action successful!'); 

                // Find the form associated with the clicked button
                let formToSubmit = null;
                if (trigger.form) {
                    formToSubmit = trigger.form;
                } else if (trigger.tagName === 'FORM') {
                    formToSubmit = trigger;
                }

                // Submit the form safely
                if (formToSubmit) {
                    // Optional: Attach the current URL so the processing PHP knows where to send you back
                    const returnPath = document.createElement('input');
                    returnPath.type = 'hidden';
                    returnPath.name = 'redirect_back_to';
                    returnPath.value = window.location.href; 
                    formToSubmit.appendChild(returnPath);

                    formToSubmit.submit();
                }
            }
        };

        // Cancel Button
        const noBtn = document.createElement('button');
        noBtn.innerText = "Cancel";
        noBtn.className = "btn-custom-alert btn-alert-secondary";
        noBtn.onclick = () => { 
            overlay.style.display = 'none'; 
            document.body.style.overflow = 'auto';
        };

        // Append buttons and show modal
        container.appendChild(yesBtn);
        container.appendChild(noBtn);
        overlay.style.display = 'flex';
        
        // Prevent default browser submission until "Yes" is clicked
        return false; 
    };
})();
</script> -->
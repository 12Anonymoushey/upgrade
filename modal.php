<link rel="stylesheet" href="/upgrade/modal.css">

<div class="popup-overlay" id="modalOverlay">
    <div class="popup" id="popup">
        <img src="" id="modalIcon">
        <h2 id="modalTitle">Title</h2>
        <p id="modalMsg">Message</p>
        <div id="modalBtns" class="modal-btns"></div>
    </div>
</div>

<script>
    function triggerModal(config) {
        const popup = document.getElementById("popup");
        const overlay = document.getElementById("modalOverlay");
        
        // 1. Reset themes
        popup.classList.remove('theme-success', 'theme-error', 'theme-warning', 'theme-notification');
        
        // 2. Apply the chosen theme class
        if(config.theme) popup.classList.add('theme-' + config.theme);
        
        // 3. Set Content
        document.getElementById("modalTitle").innerText = config.title;
        document.getElementById("modalMsg").innerText = config.message;
        document.getElementById("modalIcon").src = config.icon || "assets/logo.png";
        
        const btnArea = document.getElementById("modalBtns");
        btnArea.innerHTML = "";

        // 4. Create Buttons based on Type
        if (config.type === 'confirm') {
            // Cancel Button
            const cancelBtn = document.createElement("button");
            cancelBtn.innerText = "Cancel";
            cancelBtn.className = "btn-secondary";
            cancelBtn.onclick = closeModal;
            btnArea.appendChild(cancelBtn);

            // Confirm/Action Button
            const actionBtn = document.createElement("button");
            actionBtn.innerText = config.buttonText || "Delete";
            actionBtn.className = "btn-main danger"; // You can add 'danger' class in CSS for red
            actionBtn.onclick = config.onConfirm;
            btnArea.appendChild(actionBtn);
        } else {
            // Standard Alert Button
            const okBtn = document.createElement("button");
            okBtn.innerText = config.buttonText || "OK";
            okBtn.className = "btn-main"; 
            okBtn.onclick = () => {
                closeModal();
                if (config.redirect) window.location.href = config.redirect;
            };
            btnArea.appendChild(okBtn);
        }

        // Show it
        overlay.style.display = "flex";
        setTimeout(() => popup.classList.add("open-popup"), 10);
    }

    function closeModal() {
        document.getElementById("popup").classList.remove("open-popup");
        setTimeout(() => document.getElementById("modalOverlay").style.display = "none", 300);
    }
</script>
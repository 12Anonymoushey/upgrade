function openPanel(id)
{
    let panel = document.getElementById(id);
    panel.classList.add("show");
    panel.classList.remove("hide");
}

function closePanel(id)
{
    let panel = document.getElementById(id);
    panel.classList.remove("show");
    panel.classList.add("hide");
}
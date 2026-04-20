let btnNotes = document.getElementById('btnNotes');
let btnTasks = document.getElementById('btnTasks');
let btnFeatures = document.getElementById('btnToggle');
let Notes = document.getElementById('NotesPanel');
let Tasks = document.getElementById('tasksPanel');

btnNotes.addEventListener('click', () => {
    Notes.classList.toggle('hidden');
});

btnTasks.addEventListener('click', () => {
    Tasks.classList.toggle('hidden');
});

function openPanel(id)
{
    let panel = document.getElementById(id);
    panel.classList.add('show');
    panel.classList.remove('hidden');
}
function closePanel(id)
{
    let panel = document.getElementById(id);
    panel.classList.add('hidden');
    panel.classList.remove('show');
}
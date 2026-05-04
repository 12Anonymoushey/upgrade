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

//for pomodoro
// Get HTML elements
/**
 * Call this function ONLY when the timer hits 00:00 during a "Study" phase.
 * @param {number} minutesCompleted - The total number of minutes the user just focused for (e.g., 25)
 */
function rewardPomodoroSession(minutesCompleted) {
    fetch('../processes/save_pomodoro.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        // Send the completed duration to PHP
        body: JSON.stringify({ durationMinutes: minutesCompleted }) 
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Alert the user of their reward!
            alert(data.message);
            
            // Optionally: dynamically update the Capygrass counter on the screen without reloading
            // Assuming your capygrass <p> tag has an ID or you can target it. 
            // If you give your Capygrass <p> an id like id="capygrass-display", you could do:
            // let display = document.getElementById('capygrass-display');
            // display.innerText = parseInt(display.innerText) + data.pointsEarned;
        } else {
            console.error('Session failed to save:', data.message);
        }
    })
    .catch(error => {
        console.error('Error communicating with server:', error);
    });
}
const timeDisplay = document.getElementById('time');
const startBtn = document.getElementById('start-btn');
const pauseBtn = document.getElementById('pause-btn');
const resetBtn = document.getElementById('reset-btn');
const applyBtn = document.getElementById('apply-btn');
const studyInput = document.getElementById('study-input');
const breakInput = document.getElementById('break-input');
const modeIndicator = document.getElementById('mode-indicator');
const accumulatedTimeDisplay = document.getElementById('accumulated-time');

let timerId = null; 
let isStudyMode = true; 
let timeLeft = parseInt(studyInput.value) * 60;
let accumulatedTime = 0;

function updateDisplay() {
    const minutes = Math.floor(timeLeft / 60);
    const seconds = timeLeft % 60;
    const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
    const formattedSeconds = seconds < 10 ? '0' + seconds : seconds;
    timeDisplay.textContent = `${formattedMinutes}:${formattedSeconds}`;
}

function switchMode() {
    isStudyMode = !isStudyMode;
    
    if (isStudyMode) {
        rewardPomodoroSession(completedMins);
        modeIndicator.textContent = "Study Time";
        pomodoroPanel.classList.remove('break-mode'); 
        timeLeft = parseInt(studyInput.value) * 60;
    } else {
        modeIndicator.textContent = "Break Time";
        pomodoroPanel.classList.add('break-mode'); 
        timeLeft = parseInt(breakInput.value) * 60;
    }
    updateDisplay();
}

function startTimer() {
    if (timerId !== null) return; 
    
    timerId = setInterval(() => {
        timeLeft--;
        accumulatedTime++;
        
        // Auto-switch modes when time drops below 0
        if (timeLeft < 0) {
            switchMode();
        } else {
            updateDisplay();
        }
    }, 1000);
}

function pauseTimer() {
    clearInterval(timerId);
    timerId = null;
}

function resetTimer() {
    pauseTimer();
    isStudyMode = true; 
    pomodoroPanel.classList.remove('break-mode');
    modeIndicator.textContent = "Study Time";
    timeLeft = parseInt(studyInput.value) * 60;
    updateDisplay();
}

function applySettings() {
    if (studyInput.value > 0 && breakInput.value > 0) {
        resetTimer(); 
    } else {
        alert("Please enter values greater than 0.");
    }
}

// Event Listeners
startBtn.addEventListener('click', startTimer);
pauseBtn.addEventListener('click', pauseTimer);
resetBtn.addEventListener('click', resetTimer);
applyBtn.addEventListener('click', applySettings);

// Initialize
updateDisplay();

let currentQuizCards = [];
let currentQuizIndex = 0;

// --- MY QUIZZES NAVIGATION ---
const btnMyQuizzes = document.getElementById('btnMyQuizzes');
const myQuizzesPanel = document.getElementById('myQuizzesPanel');

if (btnMyQuizzes && myQuizzesPanel) {
    btnMyQuizzes.addEventListener('click', () => {
        // Hide other panels and show this one
        myQuizzesPanel.classList.toggle('hidden');
        if (!myQuizzesPanel.classList.contains('hidden')) {
            loadMyDecks(); // Fetch decks when opened
        }
    });
}

// --- DECK MANAGEMENT ---

function loadMyDecks() {
    let formData = new FormData();
    formData.append('action', 'get_my_decks');

    fetch('quiz_process.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(decks => {
        const list = document.getElementById('my-decks-list');
        list.innerHTML = '';
        decks.forEach(deck => {
            list.innerHTML += `
                <li style="background: #fdfbf3; padding: 15px; margin-bottom: 10px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border-left: 4px solid var(--primary);">
                    <strong>${deck.title}</strong>
                    <div style="display: flex; gap: 5px;">
                        <button class="action-btn" onclick="openEditDeck(${deck.deck_id}, '${deck.title}')" style="background: #3498db; width: auto; padding: 5px 10px;">Edit Cards</button>
                        <button class="action-btn" onclick="deleteDeck(${deck.deck_id})" style="background: #e74c3c; width: auto; padding: 5px 10px;">Delete</button>
                    </div>
                </li>
            `;
        });
        if(decks.length === 0) list.innerHTML = "<li>You haven't created any quizzes yet.</li>";
    });
}

function createDeck() {
    const title = document.getElementById('new-deck-title').value.trim();
    if (!title) return alert("Please enter a title!");

    let formData = new FormData();
    formData.append('action', 'create_deck');
    formData.append('title', title);

    fetch('quiz_process.php', { method: 'POST', body: formData })
    .then(res => res.text())
    .then(msg => {
        document.getElementById('new-deck-title').value = ''; // Clear input
        loadMyDecks(); // Refresh list
    });
}

function deleteDeck(deckId) {
    if (!confirm("Are you sure? This will delete the deck and ALL its flashcards!")) return;

    let formData = new FormData();
    formData.append('action', 'delete_deck');
    formData.append('deck_id', deckId);

    fetch('quiz_process.php', { method: 'POST', body: formData })
    .then(() => loadMyDecks());
}

// --- FLASHCARD MANAGEMENT ---

function openEditDeck(deckId, title) {
    document.getElementById('my-decks-list').classList.add('hidden');
    document.getElementById('edit-deck-area').classList.remove('hidden');
    
    document.getElementById('editing-deck-title').textContent = "Editing: " + title;
    document.getElementById('edit-deck-id').value = deckId;
    
    loadMyCards(deckId);
}

function closeEditDeck() {
    document.getElementById('edit-deck-area').classList.add('hidden');
    document.getElementById('my-decks-list').classList.remove('hidden');
    loadMyDecks();
}

function loadMyCards(deckId) {
    let formData = new FormData();
    formData.append('action', 'get_my_cards');
    formData.append('deck_id', deckId);

    fetch('quiz_process.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(cards => {
        const list = document.getElementById('my-cards-list');
        list.innerHTML = '';
        cards.forEach(card => {
            list.innerHTML += `
                <li style="background: #fff; padding: 10px; margin-bottom: 5px; border: 1px solid #ddd; border-radius: 5px; display: flex; justify-content: space-between;">
                    <div>
                        <strong>Q:</strong> ${card.front_text} <br>
                        <strong>A:</strong> <span style="color: var(--primary);">${card.back_text}</span>
                    </div>
                    <button onclick="deleteCard(${card.card_id})" style="background: transparent; border: none; color: #e74c3c; cursor: pointer; font-size: 1.2rem;">&times;</button>
                </li>
            `;
        });
        if(cards.length === 0) list.innerHTML = "<p>No cards in this deck yet.</p>";
    });
}

function addCard() {
    const deckId = document.getElementById('edit-deck-id').value;
    const front = document.getElementById('new-card-front').value.trim();
    const back = document.getElementById('new-card-back').value.trim();

    if (!front || !back) return alert("Both Question and Answer are required!");

    let formData = new FormData();
    formData.append('action', 'add_card');
    formData.append('deck_id', deckId);
    formData.append('front', front);
    formData.append('back', back);

    fetch('quiz_process.php', { method: 'POST', body: formData })
    .then(() => {
        document.getElementById('new-card-front').value = '';
        document.getElementById('new-card-back').value = '';
        loadMyCards(deckId); // Refresh cards
    });
}

function deleteCard(cardId) {
    let formData = new FormData();
    formData.append('action', 'delete_card');
    formData.append('card_id', cardId);

    fetch('quiz_process.php', { method: 'POST', body: formData })
    .then(() => {
        const deckId = document.getElementById('edit-deck-id').value;
        loadMyCards(deckId); // Refresh cards
    });
}

function startCommunityQuiz(deckId) {
    let formData = new FormData();
    formData.append('action', 'fetch_cards');
    formData.append('deck_id', deckId);

    fetch('quiz_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(cards => {
        if (cards.length === 0) {
            alert("This deck is empty!");
            return;
        }
        currentQuizCards = cards;
        currentQuizIndex = 0;
        
        document.getElementById('quiz-area').classList.remove('hidden');
        document.getElementById('quiz-reward-form').classList.add('hidden');
        displayQuizCard();
    });
}

function displayQuizCard() {
    if (currentQuizIndex >= currentQuizCards.length) {
        // Quiz is finished!
        document.getElementById('quiz-front').textContent = "Deck Completed! Great job.";
        document.getElementById('quiz-back').classList.add('hidden');
        document.getElementById('quiz-show-answer').classList.add('hidden');
        document.getElementById('quiz-controls').classList.add('hidden');
        
        // Show reward button
        document.getElementById('quiz-reward-form').classList.remove('hidden');
        return;
    }

    const card = currentQuizCards[currentQuizIndex];
    document.getElementById('quiz-front').textContent = card.front_text;
    document.getElementById('quiz-back').textContent = card.back_text;
    
    document.getElementById('quiz-back').classList.add('hidden');
    document.getElementById('quiz-controls').classList.add('hidden');
    document.getElementById('quiz-show-answer').classList.remove('hidden');
}

document.getElementById('quiz-show-answer')?.addEventListener('click', () => {
    document.getElementById('quiz-back').classList.remove('hidden');
    document.getElementById('quiz-show-answer').classList.add('hidden');
    document.getElementById('quiz-controls').classList.remove('hidden');
});

document.getElementById('quiz-correct')?.addEventListener('click', () => {
    currentQuizIndex++;
    displayQuizCard();
});

document.getElementById('quiz-wrong')?.addEventListener('click', () => {
    // Basic Leitner Logic: If wrong, push the card to the end of the array to review again
    let wrongCard = currentQuizCards.splice(currentQuizIndex, 1)[0];
    currentQuizCards.push(wrongCard);
    displayQuizCard();
});

// Add a button listener if you haven't already to open the panel
const btnCustomize = document.getElementById('btnCustomize'); // Assuming you made a sidebar button for this
if(btnCustomize) {
    btnCustomize.addEventListener('click', () => {
        document.getElementById('customizePanel').classList.toggle('hidden');
    });
}

function saveCustomization(type, value = null) {
    let formData = new FormData();
    formData.append('action', 'save_customization');
    formData.append('type', type);

    if (type === 'personal') {
        formData.append('fname', document.getElementById('cust-fname').value);
        formData.append('mname', document.getElementById('cust-mname').value);
        formData.append('lname', document.getElementById('cust-lname').value);
    } else if (type === 'username') {
        formData.append('username', document.getElementById('cust-username').value);
    } else {
        formData.append('value', value); // For the 3 color pickers
    }

    fetch('customize_process.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(msg => {
        if(msg.includes("Success")) {
            // Instantly apply color changes to the CSS root variables without reloading
            if (type === 'bg_color') document.documentElement.style.setProperty('--background', value);
            if (type === 'btn_color') document.documentElement.style.setProperty('--primary', value);
            if (type === 'theme_color') document.documentElement.style.setProperty('--secondary', value);
            
            if(type === 'personal' || type === 'username') alert("Saved successfully!");
        } else {
            alert(msg);
        }
    });
}

function saveBanner(e) {
    e.preventDefault();
    let formData = new FormData(document.getElementById('banner-form'));
    formData.append('action', 'save_banner');

    fetch('customize_process.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(msg => {
        alert(msg);
        if(msg.includes("Success")) {
            location.reload(); // Reload to show the new banner image
        }
    });
}
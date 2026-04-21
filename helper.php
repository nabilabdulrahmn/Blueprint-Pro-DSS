<!-- Helper AI Widget -->
<div class="ai-helper-widget" id="aiHelperWidget">
    <div class="ai-helper-panel" id="aiHelperPanel">
        <div class="ai-helper-header">
            Blueprint Support
            <button class="ai-helper-close" onclick="toggleHelper()"><i class='bx bx-x'></i></button>
        </div>
        <div class="ai-helper-body" id="aiHelperBody">
            <p>Strategic initialization complete. I am your **Blueprint Support** interface. How shall we architect your next execution today?</p>
            <div class="ai-helper-options">
                <button onclick="helperAction('guide')">Show me how it works</button>
                <button onclick="helperAction('bep')">What is BEP?</button>
            </div>
        </div>
    </div>
    <div class="ai-helper-avatar-wrapper" onclick="toggleHelper()">
        <div class="ai-helper-avatar">
            <img src="assets/img/helper.png" alt="AI Helper">
        </div>
        <div class="ai-helper-badge">1</div>
    </div>
</div>

<script>
function toggleHelper() {
    const panel = document.getElementById('aiHelperPanel');
    panel.classList.toggle('active');
    
    // Hide badge once opened
    const badge = document.querySelector('.ai-helper-badge');
    if(badge) badge.style.display = 'none';
}

function helperAction(action) {
    const body = document.getElementById('aiHelperBody');
    if (action === 'guide') {
        window.location.href = 'guide.php';
    } else if (action === 'bep') {
        body.innerHTML = '<p><strong>Break-Even Point (BEP)</strong> is the number of units you need to sell to cover all your costs (booth, labor, transpo). Any sales beyond this point are pure profit!</p><button onclick="resetHelper()" class="ai-helper-back-btn">← Back</button>';
    }
}

function resetHelper() {
    const body = document.getElementById('aiHelperBody');
    body.innerHTML = `
        <p>Strategic initialization complete. I am your **Blueprint Support** interface. How shall we architect your next execution today?</p>
        <div class="ai-helper-options">
            <button onclick="helperAction('guide')">Show me how it works</button>
            <button onclick="helperAction('bep')">What is BEP?</button>
        </div>
    `;
}
</script>

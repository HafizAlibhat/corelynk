// Quick JavaScript test to inject into browser console
// Copy and paste this into your browser's Developer Tools Console

console.log('=== Debugging View Processes & Batches Button ===');

// Check if the button exists
const buttons = document.querySelectorAll('.view-processes');
console.log('Found buttons:', buttons.length);

buttons.forEach((btn, index) => {
    console.log(`Button ${index}:`, {
        itemId: btn.dataset.itemId,
        productId: btn.dataset.productId,
        text: btn.textContent.trim()
    });
});

// Check if the function exists
console.log('fetchAndRenderProcesses function exists:', typeof fetchAndRenderProcesses);

// Test the button click manually
if (buttons.length > 0) {
    const btn = buttons[0];
    console.log('Testing first button click...');
    
    // Manual test
    try {
        console.log('Calling fetchAndRenderProcesses manually...');
        fetchAndRenderProcesses(btn.dataset.itemId, btn.dataset.productId);
    } catch (error) {
        console.error('Error calling fetchAndRenderProcesses:', error);
    }
}

// Check for containers
buttons.forEach((btn, index) => {
    const container = document.getElementById('processes-for-item-' + btn.dataset.itemId);
    console.log(`Container for item ${btn.dataset.itemId}:`, container ? 'EXISTS' : 'MISSING');
});

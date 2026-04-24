document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('budgettracker_theme') || 'light';
    applyTheme(savedTheme);
    
    document.querySelectorAll('.theme-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const theme = e.currentTarget.dataset.theme;
            applyTheme(theme);
            saveTheme(theme);
        });
    });
});

function applyTheme(themeName) {
    document.documentElement.setAttribute('data-theme', themeName);
    
    document.querySelectorAll('.theme-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.theme === themeName);
    });
}

function saveTheme(themeName) {
    localStorage.setItem('budgettracker_theme', themeName);
    
    fetch('api/theme.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: themeName })
    }).catch(err => console.log('Theme save error:', err));
}
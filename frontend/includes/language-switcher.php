<?php
// Language switcher component
// This file should be included in layouts where language switching is needed

// Ensure language system is loaded
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/language.php';

$currentLang = getCurrentLanguage();
?>

<!-- Language Switcher Dropdown -->
<div class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
       <img src="https://flagcdn.com/w20/<?php echo $currentLang === 'vi' ? 'vn' : 'gb'; ?>.png" 
           alt="<?php echo $currentLang === 'vi' ? __('language_vietnamese') : __('language_english'); ?>" 
           style="width: 20px; height: auto; margin-right: 5px;">
       <?php echo $currentLang === 'vi' ? __('lang_short_vi') : __('lang_short_en'); ?>
    </a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
        <li>
            <a href="#" class="dropdown-item <?php echo $currentLang === 'vi' ? 'active' : ''; ?>" 
               onclick="changeLanguage('vi'); return false;">
                <img src="https://flagcdn.com/w20/vn.png" alt="<?php echo htmlspecialchars(__('language_vietnam_alt')); ?>" style="width: 20px; height: auto; margin-right: 8px;">
                <?php echo __('language_vietnamese'); ?>
            </a>
        </li>
        <li>
            <a href="#" class="dropdown-item <?php echo $currentLang === 'en' ? 'active' : ''; ?>" 
               onclick="changeLanguage('en'); return false;">
                <img src="https://flagcdn.com/w20/gb.png" alt="<?php echo htmlspecialchars(__('language_english_alt')); ?>" style="width: 20px; height: auto; margin-right: 8px;">
                <?php echo __('language_english'); ?>
            </a>
        </li>
    </ul>
</div>

<style>
.dropdown-item.active {
    background-color: var(--bs-primary);
    color: white;
}

.dropdown-item:hover {
    background-color: var(--bs-secondary);
}
</style>

<script>
function changeLanguage(lang) {
    // Set language in session via AJAX without page redirect
    fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'change_language=1&language=' + lang + '&csrf_token=<?php echo getCsrfToken(); ?>'
    }).then(function() {
        // Reload current page to apply new language
        window.location.reload();
    }).catch(function(error) {
        console.error('Error changing language:', error);
    });
}
</script>

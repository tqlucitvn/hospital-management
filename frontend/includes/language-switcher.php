<?php
// Language switcher component
// This file should be included in layouts where language switching is needed

// Ensure language system is loaded
require_once __DIR__ . '/language.php';

$currentLang = getCurrentLanguage();
?>

<!-- Language Switcher Dropdown -->
<div class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="languageDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <img src="https://flagcdn.com/w20/<?php echo $currentLang === 'vi' ? 'vn' : 'gb'; ?>.png" 
             alt="<?php echo $currentLang === 'vi' ? 'Tiếng Việt' : 'English'; ?>" 
             style="width: 20px; height: auto; margin-right: 5px;">
        <?php echo $currentLang === 'vi' ? 'VI' : 'EN'; ?>
    </a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="languageDropdown">
        <li>
            <form method="POST" action="change-language.php" class="d-inline">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="language" value="vi">
                <button type="submit" class="dropdown-item <?php echo $currentLang === 'vi' ? 'active' : ''; ?>">
                    <img src="https://flagcdn.com/w20/vn.png" alt="Vietnam" style="width: 20px; height: auto; margin-right: 8px;">
                    Tiếng Việt
                </button>
            </form>
        </li>
        <li>
            <form method="POST" action="change-language.php" class="d-inline">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="language" value="en">
                <button type="submit" class="dropdown-item <?php echo $currentLang === 'en' ? 'active' : ''; ?>">
                    <img src="https://flagcdn.com/w20/gb.png" alt="English" style="width: 20px; height: auto; margin-right: 8px;">
                    English
                </button>
            </form>
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

.dropdown-item button {
    background: none;
    border: none;
    width: 100%;
    text-align: left;
    padding: 0;
}
</style>

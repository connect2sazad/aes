<?php
function hasDemoContent(): bool {
    $columns=(int)query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name IN ('projects','posts','reviews') AND column_name='demo_key'")->fetchColumn();
    if($columns!==3)return false;
    return (int)query("SELECT (SELECT COUNT(*) FROM projects WHERE demo_key IS NOT NULL AND published=1)+(SELECT COUNT(*) FROM posts WHERE demo_key IS NOT NULL AND published=1)+(SELECT COUNT(*) FROM reviews WHERE demo_key IS NOT NULL AND published=1)")->fetchColumn()>0;
}
function renderDemoNotice(): void {
    if(hasDemoContent())echo '<aside class="demo-notice" role="note">DEMONSTRATION CONTENT · Items marked DEMO are fictional samples, not completed installations or genuine customer testimonials.</aside>';
}
function renderDeveloperCredit(): void {
    echo '<p class="developer-credit">Developed by <a href="https://connect2sazad.github.io/" target="_blank" rel="noopener noreferrer">connect2sazad</a></p>';
}

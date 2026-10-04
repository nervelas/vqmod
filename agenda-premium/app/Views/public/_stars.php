<?php
/** Estrellas de calificación. Variables: $rating (1-5) */
$rating = max(0, min(5, (int) ($rating ?? 0)));
?>
<span class="pub-stars" role="img" aria-label="<?= e($rating) ?> de 5 estrellas"><?php for ($i = 1; $i <= 5; $i++) : ?><svg class="st<?= $i <= $rating ? ' on' : '' ?>" viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path d="M10 1.8l2.5 5.3 5.8.8-4.2 4.1 1 5.8L10 15l-5.1 2.8 1-5.8L1.7 7.9l5.8-.8z"/></svg><?php endfor; ?></span>

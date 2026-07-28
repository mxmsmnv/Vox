<?php namespace ProcessWire;
/**
 * Vox — Answers mode default assembly.
 */
$vox = $vox ?? wire('modules')->get('Vox');
$questionKey = wire('input')->get('question');
$voxAnswersMainTag = !empty($voxEmbedded) ? 'div' : 'main';
?>

<div class="vox-answers-layout">
    <<?= $voxAnswersMainTag ?> class="vox-answers-layout__main">
        <?php
        if ($questionKey) {
            include __DIR__ . '/vox.answers.question.php';
        } else {
            include __DIR__ . '/vox.answers.index.php';
            include __DIR__ . '/vox.answers.ask.php';
        }
        ?>
    </<?= $voxAnswersMainTag ?>>
    <aside class="vox-answers-layout__side">
        <?php include __DIR__ . '/vox.answers.sidebar.php'; ?>
    </aside>
</div>

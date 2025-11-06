<?php
declare(strict_types=1);

use GlobalLandingPage\Form\ConfigForm;
use Laminas\Form\FormInterface;
use Laminas\View\Renderer\PhpRenderer;

/**
 * @var PhpRenderer $this
 * @var ConfigForm|FormInterface $form
 * @var array<int,array{id:?int,src:string,alt:?string,label:?string,is_default?:bool}> $selectedLogos
 */

$form->prepare();
$this->headScript()->appendFile($this->assetUrl('js/admin-config.js', 'GlobalLandingPage'));
echo $this->formCollection($form, false);

$logoPreview = is_array($selectedLogos ?? null) ? $selectedLogos : [];
if ($logoPreview !== []) :
    ?>
<div class="glp-admin-logo-preview">
    <h3><?= $this->translate('Current header logos'); ?></h3>
    <ul class="glp-admin-logo-preview__list">
        <?php foreach ($logoPreview as $logo) : ?>
            <li class="glp-admin-logo-preview__item">
                <figure>
                    <img
                        src="<?= $this->escapeHtmlAttr($logo['src']); ?>"
                        alt="<?= $this->escapeHtmlAttr($logo['alt'] ?? ''); ?>"
                        loading="lazy"
                    >
                    <?php if (!empty($logo['label'])) : ?>
                        <figcaption><?= $this->escapeHtml($logo['label']); ?></figcaption>
                    <?php endif; ?>
                </figure>
                <?php
                $isDefault = !empty($logo['is_default']);
                $metaLabel = $isDefault
                    ? $this->translate('Default logo')
                    : sprintf($this->translate('Asset #%d'), (int) ($logo['id'] ?? 0));
                ?>
                <p class="glp-admin-logo-preview__meta">
                    <?= $this->escapeHtml($metaLabel); ?>
                </p>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
    <?php
endif;

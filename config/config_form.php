<?php
declare(strict_types=1);

use GlobalLandingPage\Form\ConfigForm;
use Laminas\Form\FormInterface;
use Laminas\View\Renderer\PhpRenderer;

/**
 * @var PhpRenderer $this
 * @var ConfigForm|FormInterface $form
 */

$form->prepare();
$this->headScript()->appendFile($this->assetUrl('js/admin-config.js', 'GlobalLandingPage'));
echo $this->formCollection($form, false);

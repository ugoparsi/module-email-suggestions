<?php

/**
 * SPDX-FileCopyrightText: 2026 Ugo Parsi
 * SPDX-License-Identifier: AFL-3.0
 */

declare(strict_types=1);

class UgoParsi_EmailSuggestions_DomainController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/email-suggestions/domain/check', name: 'email-suggestions.domain.check', methods: ['POST'])]
    public function checkAction(): void
    {
        $domain = $this->getRequest()->getParam('domain');
        $status = Mage::getService('emailsuggestions/domain')->checkForStorefront($domain);
        $this->getResponse()->setHeader('Cache-Control', 'no-store', true)->setBodyJson(['status' => $status]);
    }
}

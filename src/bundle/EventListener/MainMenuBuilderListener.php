<?php

declare(strict_types=1);

namespace Masilia\Bundle\AiAssistant\EventListener;

use Ibexa\AdminUi\Menu\Event\ConfigureMenuEvent;
use Knp\Menu\ItemInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds the "AI Assistant" entry under the Admin menu group in the Ibexa sidebar.
 */
class MainMenuBuilderListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [ConfigureMenuEvent::MAIN_MENU => 'onMainMenuBuild'];
    }

    public function onMainMenuBuild(ConfigureMenuEvent $event): void
    {
        $this->addAiSubMenu($event->getMenu());
    }

    private function addAiSubMenu(ItemInterface $menu): void
    {
        $adminMenu = $menu->getChild('main__admin');

        if ($adminMenu === null) {
            return;
        }

        $adminMenu->addChild('main__admin__ai_settings', [
            'route' => 'app.admin.ai_settings.index',
        ])
            ->setLabel('AI Assistant')
            ->setExtra('translation_domain', 'ibexa_menu')
            ->setExtra('icon', 'settings-block')
            ->setExtra('orderNumber', 150);
    }
}

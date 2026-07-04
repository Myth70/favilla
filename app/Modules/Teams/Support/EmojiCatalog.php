<?php

declare(strict_types=1);

namespace App\Modules\Teams\Support;

/**
 * Catalogo emoji curato per il selettore dell'input chat.
 *
 * Set leggero (~80 emoji) organizzato in 6 categorie con icone FontAwesome
 * per le tab. Restituisce sempre un array deterministico, nessun fetch I/O.
 */
final class EmojiCatalog
{
    /**
     * @return array<string, array{label: string, icon: string, emojis: array<int, string>}>
     */
    public static function inputPicker(): array
    {
        return [
            'smileys' => [
                'label'  => t('teams.emoji_category.smileys'),
                'icon'   => 'fa-face-smile',
                'emojis' => ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','😉','😊','😇','🥰','😍','🤩','😘','🤔','🤨','🧐','😎','🤓','🥳','😏','😒'],
            ],
            'gestures' => [
                'label'  => t('teams.emoji_category.gestures'),
                'icon'   => 'fa-hand',
                'emojis' => ['👍','👎','👌','🤌','🤏','✌️','🤞','🫰','🤘','👏','🙏','💪','🤝','🫶','👋'],
            ],
            'hearts' => [
                'label'  => t('teams.emoji_category.hearts'),
                'icon'   => 'fa-heart',
                'emojis' => ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','💖','💘','💝'],
            ],
            'animals' => [
                'label'  => t('teams.emoji_category.animals'),
                'icon'   => 'fa-paw',
                'emojis' => ['🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮'],
            ],
            'food' => [
                'label'  => t('teams.emoji_category.food'),
                'icon'   => 'fa-utensils',
                'emojis' => ['🍎','🍕','🍔','🍟','🍩','🍪','🍰','🍫','🍿','🥤','🍺','☕'],
            ],
            'objects' => [
                'label'  => t('teams.emoji_category.objects'),
                'icon'   => 'fa-gift',
                'emojis' => ['🎉','🔥','⭐','✅','❌','💯','🚀','🎁','💡','⚡','🌈','🎵'],
            ],
        ];
    }
}

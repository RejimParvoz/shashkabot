<?php
/**
 * Shashka Game - Telegram Configuration
 * Telegram Bot and Mini App Integration
 */

return [
    // Bot Configuration
    'bot' => [
        'token' => $_ENV['TELEGRAM_BOT_TOKEN'] ?? '',
        'username' => $_ENV['TELEGRAM_BOT_USERNAME'] ?? 'ShashkaGameBot',
        'webhook_url' => $_ENV['TELEGRAM_WEBHOOK_URL'] ?? 'https://shashka.uz/api/telegram/webhook',
        'max_connections' => 100,
        'allowed_updates' => [
            'message',
            'callback_query', 
            'inline_query',
            'pre_checkout_query',
            'successful_payment'
        ],
    ],
    
    // Mini App Configuration
    'mini_app' => [
        'url' => $_ENV['APP_URL'] ?? 'https://shashka.uz',
        'short_name' => 'shashka',
        'description' => 'Professional Checkers Game Platform',
    ],
    
    // Payment Configuration (Telegram Stars)
    'payments' => [
        'provider_token' => $_ENV['TELEGRAM_PROVIDER_TOKEN'] ?? '',
        'currency' => 'XTR', // Telegram Stars
        'test_mode' => $_ENV['APP_ENV'] === 'development',
        
        // Diamond packs pricing in Telegram Stars
        'diamond_packs' => [
            'pack_1' => [
                'title' => '💎 100 Olmos',
                'description' => 'Boshlang\'ich paket - 100 olmos',
                'price' => 10, // stars
                'diamonds' => 100,
            ],
            'pack_2' => [
                'title' => '💎 500 Olmos',
                'description' => 'Mashhur paket - 500 olmos + 50 bonus',
                'price' => 45, // stars
                'diamonds' => 500,
                'bonus' => 50,
            ],
            'pack_3' => [
                'title' => '💎 1200 Olmos',
                'description' => 'Katta paket - 1200 olmos + 200 bonus',
                'price' => 100, // stars
                'diamonds' => 1200,
                'bonus' => 200,
            ],
            'pack_4' => [
                'title' => '💎 2500 Olmos',
                'description' => 'Premium paket - 2500 olmos + 500 bonus',
                'price' => 200, // stars
                'diamonds' => 2500,
                'bonus' => 500,
            ],
            'pack_5' => [
                'title' => '💎 5000 Olmos',
                'description' => 'Mega paket - 5000 olmos + 1000 bonus',
                'price' => 380, // stars
                'diamonds' => 5000,
                'bonus' => 1000,
            ],
        ],
        
        // VIP subscriptions pricing
        'vip_subscriptions' => [
            'bronze' => [
                'title' => '🥉 Bronze VIP (1 oy)',
                'description' => 'Kunlik 5💎 + VIP nishon + bronza chat rangi',
                'price' => 50, // stars per month
                'duration' => 30, // days
            ],
            'silver' => [
                'title' => '🥈 Silver VIP (1 oy)', 
                'description' => 'Kunlik 10💎 + animatsiyali nishon + kumush chat',
                'price' => 120, // stars per month
                'duration' => 30, // days
            ],
            'gold' => [
                'title' => '🥇 Gold VIP (1 oy)',
                'description' => 'Kunlik 20💎 + oltin nishon + maxsus turnirlar + 1.5x reyting',
                'price' => 250, // stars per month
                'duration' => 30, // days
            ],
            'platinum' => [
                'title' => '💎 Platinum VIP (1 oy)',
                'description' => 'Kunlik 35💎 + olmos nishon + eksklyuziv buyumlar + 2x reyting',
                'price' => 500, // stars per month
                'duration' => 30, // days
            ],
        ],
    ],
    
    // Bot Commands
    'commands' => [
        '/start' => 'Botni ishga tushirish',
        '/help' => 'Yordam olish',
        '/play' => 'O\'yin boshlash',
        '/profile' => 'Profilni ko\'rish',
        '/shop' => 'Do\'konni ochish', 
        '/tournament' => 'Turnirlar',
        '/clan' => 'Klan',
        '/friends' => 'Do\'stlar',
        '/settings' => 'Sozlamalar',
        '/support' => 'Qo\'llab-quvvatlash',
    ],
    
    // Inline Keyboards
    'keyboards' => [
        'main_menu' => [
            [
                ['text' => '🎮 O\'yin boshlash', 'callback_data' => 'game_start'],
                ['text' => '👤 Profil', 'callback_data' => 'profile'],
            ],
            [
                ['text' => '🏪 Do\'kon', 'callback_data' => 'shop'],
                ['text' => '🏆 Turnirlar', 'callback_data' => 'tournaments'],
            ],
            [
                ['text' => '👥 Klan', 'callback_data' => 'clan'],
                ['text' => '👫 Do\'stlar', 'callback_data' => 'friends'],
            ],
            [
                ['text' => '⚙️ Sozlamalar', 'callback_data' => 'settings'],
            ],
        ],
        
        'game_modes' => [
            [
                ['text' => '⚡ Klassik (5 daq)', 'callback_data' => 'mode_classic'],
                ['text' => '🔥 Blitz (3 daq)', 'callback_data' => 'mode_blitz'],
            ],
            [
                ['text' => '💨 Bullet (1 daq)', 'callback_data' => 'mode_bullet'],
                ['text' => '🎯 Rapid (10 daq)', 'callback_data' => 'mode_rapid'],
            ],
            [
                ['text' => '🏃 Marafon (30 daq)', 'callback_data' => 'mode_marathon'],
            ],
            [
                ['text' => '🤖 Bot bilan o\'ynash', 'callback_data' => 'play_vs_bot'],
            ],
            [
                ['text' => '🔙 Ortga', 'callback_data' => 'back_main'],
            ],
        ],
        
        'bot_levels' => [
            [
                ['text' => '😊 Oson', 'callback_data' => 'bot_easy'],
                ['text' => '😐 O\'rta', 'callback_data' => 'bot_medium'],
            ],
            [
                ['text' => '😤 Qiyin', 'callback_data' => 'bot_hard'],
                ['text' => '🤯 Ekspert', 'callback_data' => 'bot_expert'],
            ],
            [
                ['text' => '🔙 Ortga', 'callback_data' => 'back_game_modes'],
            ],
        ],
    ],
    
    // Messages Templates
    'messages' => [
        'welcome' => "🎯 Shashka Game'ga xush kelibsiz!\n\n" .
                    "Bu O'zbekistondagi eng yaxshi shashka platformasi.\n\n" .
                    "🎮 O'yin boshlash uchun \"O'yin boshlash\" tugmasini bosing\n" .
                    "👤 Profilingizni ko'rish uchun \"Profil\" tugmasini bosing\n\n" .
                    "Omad tilaymiz! 🍀",
        
        'profile' => "👤 **Sizning profilingiz:**\n\n" .
                    "📊 Reyting: {rating} ({league})\n" .
                    "🎮 O'yinlar: {total_games}\n" .
                    "✅ G'alabalar: {wins} ({win_rate}%)\n" .
                    "❌ Mag'lubiyatlar: {losses}\n" .
                    "🤝 Duranglar: {draws}\n\n" .
                    "💎 Olmoslar: {diamonds}\n" .
                    "🪙 Tangalar: {coins}\n\n" .
                    "{vip_status}",
        
        'game_invite' => "🎮 **Shashka o'yiniga taklif!**\n\n" .
                        "🏷️ Rejim: {mode}\n" .
                        "⏱️ Vaqt: {time}\n" .
                        "🏆 Reyting: {rating}\n\n" .
                        "O'ynashni xohlaysizmi?",
        
        'game_started' => "🎮 **O'yin boshlandi!**\n\n" .
                         "👥 {player1} vs {player2}\n" .
                         "🏷️ Rejim: {mode}\n" .
                         "⏱️ Vaqt: {time}\n\n" .
                         "O'yinni ochish uchun tugmani bosing:",
        
        'game_finished' => "🏁 **O'yin yakunlandi!**\n\n" .
                          "🏆 G'olib: {winner}\n" .
                          "📊 Natija: {result}\n" .
                          "⏱️ Davomiyligi: {duration}\n\n" .
                          "💰 Mukofot:\n" .
                          "💎 Olmoslar: +{diamonds}\n" .
                          "🪙 Tangalar: +{coins}",
        
        'tournament_announcement' => "📢 **Yangi turnir e'lon qilinmoqda!**\n\n" .
                                   "🏆 {tournament_name}\n" .
                                   "📅 Sana: {date}\n" .
                                   "⏰ Vaqt: {time}\n" .
                                   "💰 Mukofot fondi: {prize}\n" .
                                   "👥 Ishtirokchilar: {max_players}\n\n" .
                                   "Ro'yxatdan o'ting!",
        
        'daily_bonus' => "🎁 **Kunlik mukofot!**\n\n" .
                        "Kun: {day}/7\n" .
                        "💎 Olmoslar: +{diamonds}\n" .
                        "🪙 Tangalar: +{coins}\n\n" .
                        "Ertaga ham keling! 😊",
        
        'vip_expired' => "⚠️ **VIP obunangiz tugadi!**\n\n" .
                        "VIP imtiyozlaringizni davom ettirish uchun " .
                        "yangi obuna sotib oling.\n\n" .
                        "💎 Kunlik olmoslar\n" .
                        "🏆 Maxsus turnirlar\n" .
                        "⭐ VIP nishon",
        
        'error' => "❌ Xatolik yuz berdi. Iltimos, qayta urinib ko'ring.",
        'maintenance' => "🔧 Tizim texnik xizmat ko'rsatilmoqda. Tez orada qaytamiz!",
        'banned' => "🚫 Hisobingiz bloklangan. Qo'llab-quvvatlash bilan bog'laning.",
    ],
    
    // Webhook Configuration
    'webhook' => [
        'url' => $_ENV['TELEGRAM_WEBHOOK_URL'] ?? 'https://shashka.uz/api/telegram/webhook',
        'certificate' => null, // Path to certificate file if self-signed
        'max_connections' => 100,
        'drop_pending_updates' => false,
        'secret_token' => $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? '',
        'allowed_ips' => [
            '149.154.160.0/20',
            '91.108.4.0/22',
        ],
    ],
    
    // Rate Limiting for Bot API
    'rate_limits' => [
        'messages' => [
            'per_chat' => 20,   // messages per minute per chat
            'per_second' => 30, // messages per second globally
        ],
        'callbacks' => [
            'per_user' => 100, // callback queries per minute per user
        ],
    ],
    
    // Localization
    'localization' => [
        'default_language' => 'uz',
        'supported_languages' => ['uz', 'en', 'ru'],
        'auto_detect' => true,
    ],
    
    // Analytics and Tracking
    'analytics' => [
        'track_users' => true,
        'track_commands' => true,
        'track_games' => true,
        'track_purchases' => true,
        'retention_days' => 90,
    ],
];
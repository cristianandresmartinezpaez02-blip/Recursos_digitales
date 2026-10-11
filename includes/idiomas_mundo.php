<?php
/**
 * Idiomas con traducción automática (Google Translate). Español, inglés y francés
 * tienen traducción propia (includes/idiomas/); el resto se traduce en el navegador.
 * [código de Google => [nombre en ese idioma, nombre en español, ¿se escribe de derecha a izquierda?]]
 */
const IDIOMAS_MUNDO = [
    'af' => ['Afrikaans', 'Afrikáans', false], 'sq' => ['Shqip', 'Albanés', false], 'am' => ['አማርኛ', 'Amárico', false],
    'ar' => ['العربية', 'Árabe', true], 'hy' => ['Հայերեն', 'Armenio', false], 'as' => ['অসমীয়া', 'Asamés', false],
    'ay' => ['Aymar aru', 'Aimara', false], 'az' => ['Azərbaycan', 'Azerí', false], 'bm' => ['Bamanankan', 'Bambara', false],
    'eu' => ['Euskara', 'Euskera', false], 'be' => ['Беларуская', 'Bielorruso', false], 'bn' => ['বাংলা', 'Bengalí', false],
    'bho' => ['भोजपुरी', 'Bhojpuri', false], 'bs' => ['Bosanski', 'Bosnio', false], 'bg' => ['Български', 'Búlgaro', false],
    'ca' => ['Català', 'Catalán', false], 'ceb' => ['Cebuano', 'Cebuano', false], 'ny' => ['Chichewa', 'Chichewa', false],
    'zh-CN' => ['中文（简体）', 'Chino simplificado', false], 'zh-TW' => ['中文（繁體）', 'Chino tradicional', false],
    'co' => ['Corsu', 'Corso', false], 'hr' => ['Hrvatski', 'Croata', false], 'cs' => ['Čeština', 'Checo', false],
    'da' => ['Dansk', 'Danés', false], 'dv' => ['ދިވެހި', 'Dhivehi', true], 'doi' => ['डोगरी', 'Dogri', false],
    'nl' => ['Nederlands', 'Neerlandés', false], 'eo' => ['Esperanto', 'Esperanto', false], 'et' => ['Eesti', 'Estonio', false],
    'ee' => ['Eʋegbe', 'Ewe', false], 'tl' => ['Filipino', 'Filipino', false], 'fi' => ['Suomi', 'Finés', false],
    'fy' => ['Frysk', 'Frisón', false], 'gl' => ['Galego', 'Gallego', false], 'ka' => ['ქართული', 'Georgiano', false],
    'de' => ['Deutsch', 'Alemán', false], 'el' => ['Ελληνικά', 'Griego', false], 'gn' => ['Avañe\'ẽ', 'Guaraní', false],
    'gu' => ['ગુજરાતી', 'Guyaratí', false], 'ht' => ['Kreyòl ayisyen', 'Criollo haitiano', false], 'ha' => ['Hausa', 'Hausa', false],
    'haw' => ['ʻŌlelo Hawaiʻi', 'Hawaiano', false], 'iw' => ['עברית', 'Hebreo', true], 'hi' => ['हिन्दी', 'Hindi', false],
    'hmn' => ['Hmoob', 'Hmong', false], 'hu' => ['Magyar', 'Húngaro', false], 'is' => ['Íslenska', 'Islandés', false],
    'ig' => ['Igbo', 'Igbo', false], 'ilo' => ['Ilokano', 'Ilocano', false], 'id' => ['Bahasa Indonesia', 'Indonesio', false],
    'ga' => ['Gaeilge', 'Irlandés', false], 'it' => ['Italiano', 'Italiano', false], 'ja' => ['日本語', 'Japonés', false],
    'jw' => ['Basa Jawa', 'Javanés', false], 'kn' => ['ಕನ್ನಡ', 'Canarés', false], 'kk' => ['Қазақ', 'Kazajo', false],
    'km' => ['ខ្មែរ', 'Jemer', false], 'rw' => ['Kinyarwanda', 'Kinyarwanda', false], 'gom' => ['कोंकणी', 'Konkani', false],
    'ko' => ['한국어', 'Coreano', false], 'kri' => ['Krio', 'Krio', false], 'ku' => ['Kurdî', 'Kurdo (kurmanji)', false],
    'ckb' => ['کوردی', 'Kurdo (sorani)', true], 'ky' => ['Кыргызча', 'Kirguís', false], 'lo' => ['ລາວ', 'Lao', false],
    'la' => ['Latina', 'Latín', false], 'lv' => ['Latviešu', 'Letón', false], 'ln' => ['Lingála', 'Lingala', false],
    'lt' => ['Lietuvių', 'Lituano', false], 'lg' => ['Luganda', 'Luganda', false], 'lb' => ['Lëtzebuergesch', 'Luxemburgués', false],
    'mk' => ['Македонски', 'Macedonio', false], 'mai' => ['मैथिली', 'Maithili', false], 'mg' => ['Malagasy', 'Malgache', false],
    'ms' => ['Bahasa Melayu', 'Malayo', false], 'ml' => ['മലയാളം', 'Malayalam', false], 'mt' => ['Malti', 'Maltés', false],
    'mi' => ['Māori', 'Maorí', false], 'mr' => ['मराठी', 'Maratí', false], 'mni-Mtei' => ['ꯃꯤꯇꯩꯂꯣꯟ', 'Manipurí', false],
    'lus' => ['Mizo ṭawng', 'Mizo', false], 'mn' => ['Монгол', 'Mongol', false], 'my' => ['မြန်မာ', 'Birmano', false],
    'ne' => ['नेपाली', 'Nepalí', false], 'no' => ['Norsk', 'Noruego', false], 'or' => ['ଓଡ଼ିଆ', 'Oriya', false],
    'om' => ['Afaan Oromoo', 'Oromo', false], 'ps' => ['پښتو', 'Pastún', true], 'fa' => ['فارسی', 'Persa', true],
    'pl' => ['Polski', 'Polaco', false], 'pt' => ['Português', 'Portugués', false], 'pa' => ['ਪੰਜਾਬੀ', 'Panyabí', false],
    'qu' => ['Runasimi', 'Quechua', false], 'ro' => ['Română', 'Rumano', false], 'ru' => ['Русский', 'Ruso', false],
    'sm' => ['Gagana Samoa', 'Samoano', false], 'sa' => ['संस्कृतम्', 'Sánscrito', false], 'gd' => ['Gàidhlig', 'Gaélico escocés', false],
    'nso' => ['Sepedi', 'Sepedi', false], 'sr' => ['Српски', 'Serbio', false], 'st' => ['Sesotho', 'Sesoto', false],
    'sn' => ['chiShona', 'Shona', false], 'sd' => ['سنڌي', 'Sindi', true], 'si' => ['සිංහල', 'Cingalés', false],
    'sk' => ['Slovenčina', 'Eslovaco', false], 'sl' => ['Slovenščina', 'Esloveno', false], 'so' => ['Soomaali', 'Somalí', false],
    'su' => ['Basa Sunda', 'Sundanés', false], 'sw' => ['Kiswahili', 'Suajili', false], 'sv' => ['Svenska', 'Sueco', false],
    'tg' => ['Тоҷикӣ', 'Tayiko', false], 'ta' => ['தமிழ்', 'Tamil', false], 'tt' => ['Татар', 'Tártaro', false],
    'te' => ['తెలుగు', 'Telugu', false], 'th' => ['ไทย', 'Tailandés', false], 'ti' => ['ትግርኛ', 'Tigriña', false],
    'ts' => ['Xitsonga', 'Tsonga', false], 'tr' => ['Türkçe', 'Turco', false], 'tk' => ['Türkmen', 'Turcomano', false],
    'ak' => ['Twi', 'Twi', false], 'uk' => ['Українська', 'Ucraniano', false], 'ur' => ['اردو', 'Urdu', true],
    'ug' => ['ئۇيغۇرچە', 'Uigur', true], 'uz' => ['Oʻzbek', 'Uzbeko', false], 'vi' => ['Tiếng Việt', 'Vietnamita', false],
    'cy' => ['Cymraeg', 'Galés', false], 'xh' => ['isiXhosa', 'Xhosa', false], 'yi' => ['ייִדיש', 'Yidis', true],
    'yo' => ['Yorùbá', 'Yoruba', false], 'zu' => ['isiZulu', 'Zulú', false],
];

/** Códigos de Google que no son etiquetas BCP-47 válidas para Intl / lang. */
const LOCALE_DE_GOOGLE = ['iw' => 'he', 'jw' => 'jv', 'mni-Mtei' => 'mni', 'zh-CN' => 'zh-CN', 'zh-TW' => 'zh-TW'];

/** Idioma de traducción automática elegido (cookie "googtrans" = /auto/xx) o null. */
function idiomaAutomatico(): ?string
{
    $c = $_COOKIE['googtrans'] ?? '';
    if (!preg_match('#^/[a-zA-Z-]+/([a-zA-Z-]+)$#', $c, $m)) {
        return null;
    }
    return isset(IDIOMAS_MUNDO[$m[1]]) ? $m[1] : null;
}

<?php
/**
 * Language Detection Class
 *
 * This file contains the AltSEO_AI_Plus_Language_Detector class which provides
 * comprehensive language detection functionality for the AltSEO AI Plus plugin.
 *
 * @since      1.0.0
 * @package    AltSEO_AI_Plus
 * @subpackage AltSEO_AI_Plus/includes
 * @author     Wolf Webentwicklung GmbH
 * @license    GPL-2.0-or-later
 * @link       https://www.wolfwebentwicklung.de
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Language Detection Class
 *
 * A comprehensive language detection system that supports a wide range of languages
 * using various detection methods including character patterns, word patterns, and script analysis.
 *
 * @since      1.0.0
 * @package    AltSEO_AI_Plus
 * @subpackage AltSEO_AI_Plus/includes
 * @author     Wolf Webentwicklung GmbH
 *
 * @license    GPL-2.0-or-later
 * @link       https://www.wolfwebentwicklung.de
 */
class AltSEO_AI_Plus_Language_Detector {

	/**
	 * Language patterns and data for detection.
	 *
	 * @var array
	 */
	private $language_data = array();

	/**
	 * Constructor - Initialize language patterns
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->initialize_language_data();
	}

	/**
	 * Main language detection method
	 *
	 * @param string $text The text content to analyze.
	 * @return string The detected language name (ISO 639-1 compliant)
	 * @since 1.0.0
	 */
	public function detect( $text ) {
		// Clean and normalize text.
		$clean_text = wp_strip_all_tags( $text );
		$clean_text = preg_replace( '/\s+/', ' ', trim( $clean_text ) );

		// If text is too short, return English as default.
		if ( strlen( $clean_text ) < 20 ) {
			return 'English';
		}

		// Take a sample of the text for analysis (first 1000 characters for better accuracy).
		$sample_text = substr( $clean_text, 0, 1000 );

		// Step 1: Check for script-based languages (highest priority).
		$script_language = $this->detect_by_script( $sample_text );
		if ( $script_language ) {
			return $script_language;
		}

		// Step 2: Statistical analysis for European and other languages.
		$statistical_language = $this->detect_by_statistical_analysis( $sample_text );
		if ( $statistical_language ) {
			return $statistical_language;
		}

		// Step 3: Character frequency analysis.
		$frequency_language = $this->detect_by_character_frequency( $sample_text );
		if ( $frequency_language ) {
			return $frequency_language;
		}

		// Default fallback.
		return 'English';
	}

	/**
	 * Detect language by script/writing system
	 *
	 * @param string $text Text to analyze.
	 * @return string|null Detected language or null
	 * @since 1.0.0
	 */
	private function detect_by_script( $text ) {
		// Unicode script patterns for major writing systems.
		$script_patterns = array(
			'Arabic'              => '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}]/u',
			'Chinese'             => '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u',
			'Japanese'            => '/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{31F0}-\x{31FF}]/u',
			'Korean'              => '/[\x{AC00}-\x{D7AF}\x{1100}-\x{11FF}\x{3130}-\x{318F}]/u',
			'Thai'                => '/[\x{0E00}-\x{0E7F}]/u',
			'Hebrew'              => '/[\x{0590}-\x{05FF}]/u',
			'Devanagari'          => '/[\x{0900}-\x{097F}]/u', // Hindi, Sanskrit, Marathi, etc.
			'Bengali'             => '/[\x{0980}-\x{09FF}]/u',
			'Tamil'               => '/[\x{0B80}-\x{0BFF}]/u',
			'Telugu'              => '/[\x{0C00}-\x{0C7F}]/u',
			'Kannada'             => '/[\x{0C80}-\x{0CFF}]/u',
			'Malayalam'           => '/[\x{0D00}-\x{0D7F}]/u',
			'Gujarati'            => '/[\x{0A80}-\x{0AFF}]/u',
			'Punjabi'             => '/[\x{0A00}-\x{0A7F}]/u',
			'Oriya'               => '/[\x{0B00}-\x{0B7F}]/u',
			'Myanmar'             => '/[\x{1000}-\x{109F}]/u',
			'Khmer'               => '/[\x{1780}-\x{17FF}]/u',
			'Lao'                 => '/[\x{0E80}-\x{0EFF}]/u',
			'Georgian'            => '/[\x{10A0}-\x{10FF}]/u',
			'Armenian'            => '/[\x{0530}-\x{058F}]/u',
			'Ethiopian'           => '/[\x{1200}-\x{137F}]/u',
			'Cherokee'            => '/[\x{13A0}-\x{13FF}]/u',
			'Canadian_Aboriginal' => '/[\x{1400}-\x{167F}]/u',
		);

		// Check each script pattern.
		foreach ( $script_patterns as $language => $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				// For scripts that represent multiple languages, do additional checks.
				if ( 'Devanagari' === $language ) {
					return $this->detect_devanagari_language( $text );
				}
				return $language;
			}
		}

		return null;
	}

	/**
	 * Detect specific language within Devanagari script
	 *
	 * @param string $text Text to analyze.
	 * @return string Detected language
	 */
	private function detect_devanagari_language( $text ) {
		// Hindi-specific patterns.
		$hindi_patterns = array( 'है', 'का', 'की', 'के', 'में', 'से', 'को', 'और', 'यह', 'वह' );
		$hindi_score    = 0;

		foreach ( $hindi_patterns as $pattern ) {
			$hindi_score += substr_count( $text, $pattern );
		}

		// If we have Hindi patterns, it's likely Hindi.
		if ( $hindi_score > 0 ) {
			return 'Hindi';
		}

		// Default to Hindi for Devanagari script.
		return 'Hindi';
	}

	/**
	 * Statistical analysis based on word patterns and character combinations
	 *
	 * @param string $text Text to analyze.
	 * @return string|null Detected language or null
	 */
	private function detect_by_statistical_analysis( $text ) {
		$language_scores = array();
		$text_lower      = mb_strtolower( $text, 'UTF-8' );

		foreach ( $this->language_data as $language => $data ) {
			$score = 0;

			// Score based on common words (high weight).
			foreach ( $data['words'] as $word ) {
				$word_pattern = '/\b' . preg_quote( $word, '/' ) . '\b/u';
				$word_count   = preg_match_all( $word_pattern, $text_lower );
				$score       += $word_count * 5; // High weight for words.
			}

			// Score based on special characters (medium weight).
			foreach ( $data['chars'] as $char ) {
				$char_count = mb_substr_count( $text_lower, $char, 'UTF-8' );
				$score     += $char_count * 2; // Medium weight for special chars.
			}

			// Score based on character combinations (low weight).
			if ( isset( $data['patterns'] ) ) {
				foreach ( $data['patterns'] as $pattern ) {
					$pattern_count = preg_match_all( '/' . $pattern . '/u', $text_lower );
					$score        += $pattern_count * 1; // Low weight for patterns.
				}
			}

			$language_scores[ $language ] = $score;
		}

		// Get the language with highest score.
		if ( ! empty( $language_scores ) ) {
			$max_score = max( $language_scores );
			if ( $max_score > 2 ) { // Minimum threshold.
				return array_search( $max_score, $language_scores, true );
			}
		}

		return null;
	}

	/**
	 * Character frequency analysis for language detection
	 *
	 * @param string $text Text to analyze.
	 * @return string|null Detected language or null
	 */
	private function detect_by_character_frequency( $text ) {
		// Character frequency patterns for different languages.
		$frequency_patterns = array(
			'Finnish'    => array( 'ä', 'ö', 'y' ),
			'Swedish'    => array( 'å', 'ä', 'ö' ),
			'Norwegian'  => array( 'æ', 'ø', 'å' ),
			'Danish'     => array( 'æ', 'ø', 'å' ),
			'Icelandic'  => array( 'þ', 'ð', 'æ' ),
			'Polish'     => array( 'ą', 'ć', 'ę', 'ł', 'ń', 'ó', 'ś', 'ź', 'ż' ),
			'Czech'      => array( 'á', 'č', 'ď', 'é', 'ě', 'í', 'ň', 'ó', 'ř', 'š', 'ť', 'ú', 'ů', 'ý', 'ž' ),
			'Slovak'     => array( 'á', 'ä', 'č', 'ď', 'é', 'í', 'ĺ', 'ľ', 'ň', 'ó', 'ô', 'ŕ', 'š', 'ť', 'ú', 'ý', 'ž' ),
			'Hungarian'  => array( 'á', 'é', 'í', 'ó', 'ö', 'ő', 'ú', 'ü', 'ű' ),
			'Romanian'   => array( 'ă', 'â', 'î', 'ș', 'ț' ),
			'Croatian'   => array( 'č', 'ć', 'đ', 'š', 'ž' ),
			'Serbian'    => array( 'č', 'ć', 'đ', 'š', 'ž' ),
			'Slovenian'  => array( 'č', 'š', 'ž' ),
			'Lithuanian' => array( 'ą', 'č', 'ę', 'ė', 'į', 'š', 'ų', 'ū', 'ž' ),
			'Latvian'    => array( 'ā', 'č', 'ē', 'ģ', 'ī', 'ķ', 'ļ', 'ņ', 'š', 'ū', 'ž' ),
			'Estonian'   => array( 'ä', 'ö', 'ü', 'õ' ),
			'Turkish'    => array( 'ç', 'ğ', 'ı', 'ö', 'ş', 'ü' ),
		);

		$text_lower = mb_strtolower( $text, 'UTF-8' );
		$best_match = null;
		$best_score = 0;

		foreach ( $frequency_patterns as $language => $chars ) {
			$score = 0;
			foreach ( $chars as $char ) {
				$count  = mb_substr_count( $text_lower, $char, 'UTF-8' );
				$score += $count;
			}

			if ( $score > $best_score ) {
				$best_score = $score;
				$best_match = $language;
			}
		}

		return $best_score > 2 ? $best_match : null;
	}

	/**
	 * Initialize comprehensive language data
	 */
	private function initialize_language_data() {
		$this->language_data = array(
			'English'    => array(
				'words'    => array( 'the', 'be', 'to', 'of', 'and', 'a', 'in', 'that', 'have', 'i', 'it', 'for', 'not', 'on', 'with', 'he', 'as', 'you', 'do', 'at', 'this', 'but', 'his', 'by', 'from', 'they', 'we', 'say', 'her', 'she' ),
				'chars'    => array(),
				'patterns' => array( 'th', 'he', 'in', 'er', 'an', 're', 'ed', 'nd', 'on', 'en' ),
			),
			'Spanish'    => array(
				'words'    => array( 'el', 'la', 'de', 'que', 'y', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le', 'da', 'su', 'por', 'son', 'con', 'para', 'del', 'los', 'las', 'una', 'pero', 'todo', 'bien', 'fue', 'muy', 'hasta', 'desde' ),
				'chars'    => array( 'ñ', 'á', 'é', 'í', 'ó', 'ú', '¿', '¡' ),
				'patterns' => array( 'ción', 'idad', 'mente', 'endo', 'ando' ),
			),
			'French'     => array(
				'words'    => array( 'le', 'de', 'et', 'à', 'un', 'il', 'être', 'avoir', 'que', 'pour', 'dans', 'ce', 'son', 'une', 'sur', 'avec', 'ne', 'se', 'pas', 'tout', 'plus', 'par', 'grand', 'mais', 'qui', 'lui', 'où', 'très', 'sans', 'chez' ),
				'chars'    => array( 'à', 'é', 'è', 'ê', 'ë', 'î', 'ï', 'ô', 'ù', 'û', 'ü', 'ÿ', 'ç' ),
				'patterns' => array( 'tion', 'ment', 'eux', 'ique', 'oir' ),
			),
			'German'     => array(
				'words'    => array( 'der', 'die', 'und', 'in', 'den', 'von', 'zu', 'das', 'mit', 'sich', 'des', 'auf', 'für', 'ist', 'im', 'dem', 'nicht', 'ein', 'eine', 'als', 'auch', 'es', 'an', 'werden', 'aus', 'er', 'hat', 'dass', 'sie', 'nach' ),
				'chars'    => array( 'ä', 'ö', 'ü', 'ß' ),
				'patterns' => array( 'ung', 'keit', 'lich', 'sch', 'tch' ),
			),
			'Italian'    => array(
				'words'    => array( 'il', 'di', 'che', 'e', 'la', 'per', 'un', 'in', 'con', 'del', 'da', 'a', 'al', 'le', 'se', 'gli', 'come', 'più', 'o', 'ma', 'una', 'su', 'lo', 'anche', 'tutto', 'della', 'tra', 'quando', 'molto', 'fare' ),
				'chars'    => array( 'à', 'è', 'é', 'ì', 'í', 'ò', 'ó', 'ù', 'ú' ),
				'patterns' => array( 'zione', 'mente', 'aggio', 'ezza', 'ità' ),
			),
			'Portuguese' => array(
				'words'    => array( 'o', 'de', 'a', 'e', 'que', 'do', 'da', 'em', 'um', 'para', 'é', 'com', 'não', 'uma', 'os', 'no', 'se', 'na', 'por', 'mais', 'as', 'dos', 'como', 'mas', 'foi', 'ao', 'ele', 'das', 'tem', 'à' ),
				'chars'    => array( 'ã', 'á', 'à', 'â', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ü', 'ç' ),
				'patterns' => array( 'ção', 'mente', 'ões', 'idade', 'izar' ),
			),
			'Dutch'      => array(
				'words'    => array( 'de', 'van', 'het', 'een', 'en', 'in', 'te', 'dat', 'op', 'voor', 'met', 'als', 'zijn', 'er', 'aan', 'om', 'door', 'ze', 'dan', 'of', 'naar', 'bij', 'hij', 'heeft', 'ook', 'over', 'zich', 'uit', 'maar', 'kan' ),
				'chars'    => array( 'ij', 'oe', 'aa', 'ee', 'oo', 'uu' ),
				'patterns' => array( 'lijk', 'heid', 'isch', 'atie', 'eren' ),
			),
			'Russian'    => array(
				'words'    => array( 'в', 'и', 'не', 'на', 'с', 'что', 'а', 'по', 'это', 'как', 'его', 'к', 'он', 'до', 'за', 'для', 'от', 'же', 'то', 'но', 'или', 'ты', 'мы', 'вы', 'их', 'кто', 'уже', 'бы', 'где', 'есть' ),
				'chars'    => array( 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я' ),
				'patterns' => array( 'ость', 'ение', 'ание', 'ство', 'ный' ),
			),
			'Swahili'    => array(
				'words'    => array( 'na', 'ya', 'wa', 'ni', 'za', 'la', 'kwa', 'hii', 'kila', 'yote', 'mtu', 'watu', 'kutoka', 'kwenda', 'nyingi', 'moja', 'mbili', 'tatu', 'nne', 'tano', 'sita', 'saba', 'nane', 'tisa', 'kumi' ),
				'chars'    => array(),
				'patterns' => array( 'wa', 'ki', 'ku', 'ya', 'za', 'la', 'pa', 'mu' ),
			),
			'Vietnamese' => array(
				'words'    => array( 'và', 'của', 'có', 'trong', 'là', 'một', 'được', 'cho', 'với', 'không', 'các', 'này', 'đó', 'những', 'tại', 'từ', 'sau', 'về', 'đã', 'sẽ', 'ra', 'nó', 'họ', 'năm', 'ngày' ),
				'chars'    => array( 'ă', 'â', 'đ', 'ê', 'ô', 'ơ', 'ư' ),
				'patterns' => array( 'ng', 'nh', 'th', 'tr', 'ch', 'ph', 'qu' ),
			),
			'Indonesian' => array(
				'words'    => array( 'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'dengan', 'ini', 'itu', 'pada', 'dalam', 'tidak', 'akan', 'adalah', 'atau', 'juga', 'oleh', 'saya', 'kita', 'mereka', 'ada', 'sudah', 'bisa', 'harus', 'dapat' ),
				'chars'    => array(),
				'patterns' => array( 'ng', 'an', 'kan', 'nya', 'ter', 'ber', 'men', 'per' ),
			),
			'Malay'      => array(
				'words'    => array( 'yang', 'dan', 'di', 'ke', 'dari', 'untuk', 'dengan', 'ini', 'itu', 'pada', 'dalam', 'tidak', 'akan', 'adalah', 'atau', 'juga', 'oleh', 'saya', 'kita', 'mereka', 'ada', 'sudah', 'boleh', 'mesti', 'dapat' ),
				'chars'    => array(),
				'patterns' => array( 'ng', 'an', 'kan', 'nya', 'ter', 'ber', 'men', 'per' ),
			),
			'Filipino'   => array(
				'words'    => array( 'ang', 'ng', 'sa', 'na', 'at', 'mga', 'ay', 'para', 'hindi', 'ako', 'siya', 'kami', 'kayo', 'sila', 'ito', 'iyan', 'iyon', 'dito', 'diyan', 'doon', 'may', 'wala', 'kung', 'pero', 'kasi' ),
				'chars'    => array(),
				'patterns' => array( 'ng', 'an', 'in', 'um', 'mag', 'pag', 'ka', 'ma' ),
			),
		);
	}

	/**
	 * Get language code (ISO 639-1) from language name
	 *
	 * @param string $language_name Full language name.
	 * @return string Two-letter language code
	 */
	public function get_language_code( $language_name ) {
		$language_codes = array(
			'English'    => 'en',
			'Spanish'    => 'es',
			'French'     => 'fr',
			'German'     => 'de',
			'Italian'    => 'it',
			'Portuguese' => 'pt',
			'Dutch'      => 'nl',
			'Russian'    => 'ru',
			'Chinese'    => 'zh',
			'Japanese'   => 'ja',
			'Korean'     => 'ko',
			'Arabic'     => 'ar',
			'Hindi'      => 'hi',
			'Bengali'    => 'bn',
			'Tamil'      => 'ta',
			'Telugu'     => 'te',
			'Kannada'    => 'kn',
			'Malayalam'  => 'ml',
			'Gujarati'   => 'gu',
			'Punjabi'    => 'pa',
			'Oriya'      => 'or',
			'Thai'       => 'th',
			'Vietnamese' => 'vi',
			'Indonesian' => 'id',
			'Malay'      => 'ms',
			'Filipino'   => 'fil',
			'Swahili'    => 'sw',
			'Turkish'    => 'tr',
			'Polish'     => 'pl',
			'Czech'      => 'cs',
			'Slovak'     => 'sk',
			'Hungarian'  => 'hu',
			'Romanian'   => 'ro',
			'Croatian'   => 'hr',
			'Serbian'    => 'sr',
			'Slovenian'  => 'sl',
			'Lithuanian' => 'lt',
			'Latvian'    => 'lv',
			'Estonian'   => 'et',
			'Finnish'    => 'fi',
			'Swedish'    => 'sv',
			'Norwegian'  => 'no',
			'Danish'     => 'da',
			'Icelandic'  => 'is',
			'Hebrew'     => 'he',
			'Georgian'   => 'ka',
			'Armenian'   => 'hy',
			'Myanmar'    => 'my',
			'Khmer'      => 'km',
			'Lao'        => 'lo',
			'Ethiopian'  => 'am',
			'Cherokee'   => 'chr',
		);

		return isset( $language_codes[ $language_name ] ) ? $language_codes[ $language_name ] : 'en';
	}

	/**
	 * Get all supported languages
	 *
	 * @return array List of supported language names
	 */
	public function get_supported_languages() {
		$script_languages = array(
			'Arabic',
			'Chinese',
			'Japanese',
			'Korean',
			'Thai',
			'Hebrew',
			'Hindi',
			'Bengali',
			'Tamil',
			'Telugu',
			'Kannada',
			'Malayalam',
			'Gujarati',
			'Punjabi',
			'Oriya',
			'Myanmar',
			'Khmer',
			'Lao',
			'Georgian',
			'Armenian',
			'Ethiopian',
			'Cherokee',
		);

		$statistical_languages = array_keys( $this->language_data );

		$frequency_languages = array(
			'Finnish',
			'Swedish',
			'Norwegian',
			'Danish',
			'Icelandic',
			'Polish',
			'Czech',
			'Slovak',
			'Hungarian',
			'Romanian',
			'Croatian',
			'Serbian',
			'Slovenian',
			'Lithuanian',
			'Latvian',
			'Estonian',
			'Turkish',
		);

		return array_unique( array_merge( $script_languages, $statistical_languages, $frequency_languages ) );
	}
}

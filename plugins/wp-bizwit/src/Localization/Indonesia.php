<?php
/**
 * Indonesian business profile.
 *
 * @package WP_BizWit
 */

namespace WP_BizWit\Localization;

use WP_BizWit\Support\Settings;

/**
 * Adapts the plugin to Indonesian business practice, law and paperwork.
 *
 * This is the plugin's default profile. It targets the businesses that actually
 * use it: PT, PT Perorangan, CV, UD and koperasi, most of them UMKM, invoicing
 * other Indonesian companies and government instansi.
 *
 * The rules encoded here are the ones that change what a correct document looks
 * like, not merely what it is called:
 *
 * - Only a **PKP** (Pengusaha Kena Pajak) may charge PPN. A business below the
 *   Rp 4.8 billion annual turnover threshold is generally not required to
 *   register, and a non-PKP that puts PPN on an invoice is billing tax it has no
 *   right to collect. The tax regime setting drives this, not a free-text rate.
 * - **PPh Final UMKM** at 0.5% of gross turnover (PP 55/2022) is the regime most
 *   small businesses here actually sit in. It is a tax on the seller's turnover,
 *   not a charge added to the client's invoice, so it must never be added to an
 *   invoice total.
 * - **PPh 23** withholding means a corporate client pays you the invoice total
 *   *minus* the withheld amount and remits that portion to the state on your
 *   behalf. An invoice that ignores this does not reconcile against the bank.
 * - **Bea meterai** of Rp 10,000 is due on documents stating an amount above
 *   Rp 5,000,000 (UU 10/2020), which in practice means most kwitansi.
 *
 * Rates change with the annual budget. Everything here is a default that the
 * business can override in settings, and the wording in the UI points the user
 * at their own tax consultant rather than asserting the law.
 */
class Indonesia extends Region {

	/**
	 * Stamp duty threshold in whole rupiah, above which meterai is due.
	 *
	 * @var int
	 */
	public const METERAI_THRESHOLD = 5000000;

	/**
	 * Stamp duty payable, in whole rupiah.
	 *
	 * @var int
	 */
	public const METERAI_AMOUNT = 10000;

	/**
	 * Annual turnover in whole rupiah above which PKP registration is required.
	 *
	 * @var int
	 */
	public const PKP_THRESHOLD = 4800000000;

	/**
	 * Tax regime for a PKP that charges PPN on its invoices.
	 *
	 * @var string
	 */
	public const REGIME_PKP = 'pkp';

	/**
	 * Tax regime for a small business on final 0.5% turnover tax.
	 *
	 * @var string
	 */
	public const REGIME_UMKM_FINAL = 'umkm_final';

	/**
	 * Tax regime for a business that charges no tax on its invoices.
	 *
	 * @var string
	 */
	public const REGIME_NON_PKP = 'non_pkp';

	/**
	 * Machine-readable region identifier.
	 *
	 * @return string Region code.
	 */
	public function code(): string {
		return 'id';
	}

	/**
	 * Human-readable region name.
	 *
	 * @return string Translated label.
	 */
	public function label(): string {
		return __( 'Indonesia', 'wp-bizwit' );
	}

	/**
	 * Default currency for Indonesian businesses.
	 *
	 * @return string ISO 4217 currency code.
	 */
	public function default_currency(): string {
		return 'IDR';
	}

	/**
	 * Client types, named the way Indonesian businesses name them.
	 *
	 * @return array<string, string> Type slug mapped to label.
	 */
	public function client_types(): array {
		return array(
			'individual'   => __( 'Perorangan', 'wp-bizwit' ),
			'company'      => __( 'Perusahaan (PT / CV / UD)', 'wp-bizwit' ),
			'government'   => __( 'Instansi Pemerintah', 'wp-bizwit' ),
			'organization' => __( 'Yayasan / Koperasi / Organisasi', 'wp-bizwit' ),
		);
	}

	/**
	 * Indonesian labels for core client fields.
	 *
	 * @return array<string, string> Field name mapped to label.
	 */
	protected function field_labels(): array {
		return array(
			'legal_name'         => __( 'Nama sesuai akta', 'wp-bizwit' ),
			'tax_id'             => __( 'NPWP', 'wp-bizwit' ),
			'registration_no'    => __( 'NIB', 'wp-bizwit' ),
			'address_line1'      => __( 'Alamat (jalan dan nomor)', 'wp-bizwit' ),
			'address_line2'      => __( 'Gedung / unit', 'wp-bizwit' ),
			'city'               => __( 'Kabupaten / Kota', 'wp-bizwit' ),
			'state'              => __( 'Provinsi', 'wp-bizwit' ),
			'postal_code'        => __( 'Kode Pos', 'wp-bizwit' ),
			'payment_terms_days' => __( 'Termin pembayaran', 'wp-bizwit' ),
		);
	}

	/**
	 * Indonesian help text for core client fields.
	 *
	 * @return array<string, string> Field name mapped to help text.
	 */
	protected function field_descriptions(): array {
		return array(
			'legal_name'         => __( 'Nama badan usaha sesuai akta pendirian, jika berbeda dengan nama panggilan di atas. Nama inilah yang dicantumkan pada faktur.', 'wp-bizwit' ),
			'tax_id'             => __( 'Nomor Pokok Wajib Pajak. 16 digit (berbasis NIK) atau 15 digit format lama. Wajib dicantumkan pada faktur pajak.', 'wp-bizwit' ),
			'registration_no'    => __( 'Nomor Induk Berusaha dari sistem OSS, 13 digit. Menggantikan SIUP dan TDP.', 'wp-bizwit' ),
			'payment_terms_days' => __( 'Jumlah hari sejak tanggal faktur sampai jatuh tempo. Umumnya 14 atau 30 hari.', 'wp-bizwit' ),
		);
	}

	/**
	 * Indonesian paperwork fields stored in the client's meta column.
	 *
	 * @return array<string, array{label: string, type: string, description?: string, maxlength?: int, options?: array<string, string>}> Field key mapped to definition.
	 */
	public function meta_fields(): array {
		return array(
			'legal_form' => array(
				'label'       => __( 'Bentuk badan usaha', 'wp-bizwit' ),
				'type'        => 'select',
				'options'     => self::legal_forms(),
				'description' => __( 'Menentukan bagaimana nama klien ditulis pada faktur dan kwitansi.', 'wp-bizwit' ),
			),
			'nik'        => array(
				'label'       => __( 'NIK', 'wp-bizwit' ),
				'type'        => 'text',
				'maxlength'   => 16,
				'description' => __( 'Nomor Induk Kependudukan, 16 digit. Untuk klien perorangan, NIK kini juga berfungsi sebagai NPWP.', 'wp-bizwit' ),
			),
			'is_pkp'     => array(
				'label'       => __( 'Pengusaha Kena Pajak (PKP)', 'wp-bizwit' ),
				'type'        => 'checkbox',
				'description' => __( 'Centang bila klien berstatus PKP dan dapat menerima faktur pajak.', 'wp-bizwit' ),
			),
			'rt_rw'      => array(
				'label'     => __( 'RT / RW', 'wp-bizwit' ),
				'type'      => 'text',
				'maxlength' => 16,
			),
			'kelurahan'  => array(
				'label'     => __( 'Kelurahan / Desa', 'wp-bizwit' ),
				'type'      => 'text',
				'maxlength' => 96,
			),
			'kecamatan'  => array(
				'label'     => __( 'Kecamatan', 'wp-bizwit' ),
				'type'      => 'text',
				'maxlength' => 96,
			),
			'satker'     => array(
				'label'       => __( 'Satuan Kerja / Unit', 'wp-bizwit' ),
				'type'        => 'text',
				'maxlength'   => 191,
				'description' => __( 'Untuk klien instansi pemerintah: nama satker yang menerbitkan SPK atau kontrak.', 'wp-bizwit' ),
			),
		);
	}

	/**
	 * Legal entity forms recognised in Indonesia.
	 *
	 * @return array<string, string> Form slug mapped to label.
	 */
	public static function legal_forms(): array {
		return array(
			''              => __( '— Tidak ditentukan —', 'wp-bizwit' ),
			'pt'            => __( 'PT (Perseroan Terbatas)', 'wp-bizwit' ),
			'pt_perorangan' => __( 'PT Perorangan', 'wp-bizwit' ),
			'cv'            => __( 'CV (Commanditaire Vennootschap)', 'wp-bizwit' ),
			'firma'         => __( 'Firma', 'wp-bizwit' ),
			'ud'            => __( 'UD (Usaha Dagang)', 'wp-bizwit' ),
			'koperasi'      => __( 'Koperasi', 'wp-bizwit' ),
			'yayasan'       => __( 'Yayasan', 'wp-bizwit' ),
			'perkumpulan'   => __( 'Perkumpulan', 'wp-bizwit' ),
			'bumn'          => __( 'BUMN', 'wp-bizwit' ),
			'bumd'          => __( 'BUMD', 'wp-bizwit' ),
			'instansi'      => __( 'Instansi Pemerintah', 'wp-bizwit' ),
			'perorangan'    => __( 'Perorangan', 'wp-bizwit' ),
		);
	}

	/**
	 * Business size classes under PP 7/2021.
	 *
	 * Used to pick a sensible default tax regime and to remind the user which
	 * thresholds apply to them.
	 *
	 * @return array<string, string> Scale slug mapped to label.
	 */
	public static function business_scales(): array {
		return array(
			'mikro'    => __( 'Usaha Mikro', 'wp-bizwit' ),
			'kecil'    => __( 'Usaha Kecil', 'wp-bizwit' ),
			'menengah' => __( 'Usaha Menengah', 'wp-bizwit' ),
			'besar'    => __( 'Usaha Besar', 'wp-bizwit' ),
		);
	}

	/**
	 * Tax regimes an Indonesian business can operate under.
	 *
	 * @return array<string, string> Regime slug mapped to label.
	 */
	public static function tax_regimes(): array {
		return array(
			self::REGIME_UMKM_FINAL => __( 'UMKM — PPh Final 0,5% (PP 55/2022)', 'wp-bizwit' ),
			self::REGIME_NON_PKP    => __( 'Non-PKP — tanpa PPN', 'wp-bizwit' ),
			self::REGIME_PKP        => __( 'PKP — memungut PPN', 'wp-bizwit' ),
		);
	}

	/**
	 * Ways an Indonesian client actually pays.
	 *
	 * @return array<string, string> Method slug mapped to label.
	 */
	public function payment_methods(): array {
		return array(
			'bank_transfer' => __( 'Transfer Bank', 'wp-bizwit' ),
			'cash'          => __( 'Tunai', 'wp-bizwit' ),
			'qris'          => __( 'QRIS', 'wp-bizwit' ),
			'ewallet'       => __( 'E-Wallet (GoPay / OVO / DANA / ShopeePay)', 'wp-bizwit' ),
			'virtual_acc'   => __( 'Virtual Account', 'wp-bizwit' ),
			'giro'          => __( 'Cek / Giro', 'wp-bizwit' ),
			'card'          => __( 'Kartu Kredit / Debit', 'wp-bizwit' ),
			'sp2d'          => __( 'SP2D (pembayaran instansi pemerintah)', 'wp-bizwit' ),
			'offset'        => __( 'Kompensasi / Potongan', 'wp-bizwit' ),
			'other'         => __( 'Lainnya', 'wp-bizwit' ),
		);
	}

	/**
	 * The 38 Indonesian provinces.
	 *
	 * @return string[] Province names.
	 */
	public function provinces(): array {
		return array(
			'Aceh',
			'Sumatera Utara',
			'Sumatera Barat',
			'Riau',
			'Kepulauan Riau',
			'Jambi',
			'Sumatera Selatan',
			'Kepulauan Bangka Belitung',
			'Bengkulu',
			'Lampung',
			'DKI Jakarta',
			'Jawa Barat',
			'Jawa Tengah',
			'DI Yogyakarta',
			'Jawa Timur',
			'Banten',
			'Bali',
			'Nusa Tenggara Barat',
			'Nusa Tenggara Timur',
			'Kalimantan Barat',
			'Kalimantan Tengah',
			'Kalimantan Selatan',
			'Kalimantan Timur',
			'Kalimantan Utara',
			'Sulawesi Utara',
			'Sulawesi Tengah',
			'Sulawesi Selatan',
			'Sulawesi Tenggara',
			'Gorontalo',
			'Sulawesi Barat',
			'Maluku',
			'Maluku Utara',
			'Papua',
			'Papua Barat',
			'Papua Barat Daya',
			'Papua Selatan',
			'Papua Tengah',
			'Papua Pegunungan',
		);
	}

	/**
	 * Default sales tax label and rate.
	 *
	 * @return array{label: string, rate: string} Tax label and percentage rate.
	 */
	public function default_tax(): array {
		return array(
			'label' => __( 'PPN', 'wp-bizwit' ),
			'rate'  => '11',
		);
	}

	/**
	 * Present an NPWP the way Indonesian paperwork does.
	 *
	 * The 15-digit format issued before the NIK-based migration is conventionally
	 * written 00.000.000.0-000.000. The 16-digit identifier that replaced it is
	 * written as plain digits, so it is returned unchanged.
	 *
	 * @param string $raw NPWP as typed.
	 *
	 * @return string Formatted NPWP, or the trimmed input when it is neither length.
	 */
	public function format_tax_id( string $raw ): string {
		$digits = (string) preg_replace( '/\D/', '', $raw );

		if ( 15 !== strlen( $digits ) ) {
			return '' === $digits ? trim( $raw ) : $digits;
		}

		return sprintf(
			'%s.%s.%s.%s-%s.%s',
			substr( $digits, 0, 2 ),
			substr( $digits, 2, 3 ),
			substr( $digits, 5, 3 ),
			substr( $digits, 8, 1 ),
			substr( $digits, 9, 3 ),
			substr( $digits, 12, 3 )
		);
	}

	/**
	 * Whether an NPWP has a plausible length.
	 *
	 * Both the legacy 15-digit and the current 16-digit forms are accepted, since
	 * older client records and printed contracts still carry the old number.
	 *
	 * @param string $raw NPWP as typed.
	 *
	 * @return bool True when empty, 15 digits or 16 digits.
	 */
	public function is_valid_tax_id( string $raw ): bool {
		$digits = (string) preg_replace( '/\D/', '', $raw );

		return '' === $digits || in_array( strlen( $digits ), array( 15, 16 ), true );
	}

	/**
	 * Format a number with Indonesian separators.
	 *
	 * Indonesian usage groups thousands with a full stop and marks decimals with
	 * a comma - the opposite of English. This is applied regardless of the site
	 * locale, because a rupiah figure written "1,500,000" reads as one and a half
	 * to an Indonesian reader.
	 *
	 * @param float $value    Number to format.
	 * @param int   $decimals Decimal places to show.
	 *
	 * @return string Formatted number.
	 */
	public function format_number( float $value, int $decimals = 0 ): string {
		return number_format( $value, $decimals, ',', '.' );
	}

	/**
	 * Format a date the Indonesian way, for example "26 Juli 2026".
	 *
	 * The month names are produced here rather than via date_i18n() so that the
	 * date is correct even when the site itself runs in another locale.
	 *
	 * @param string $date Date in Y-m-d form.
	 *
	 * @return string Formatted date, or '' when the input is not a date.
	 */
	public function format_date( string $date ): string {
		$stamp = strtotime( $date );

		if ( false === $stamp ) {
			return '';
		}

		$months = array(
			1  => 'Januari',
			2  => 'Februari',
			3  => 'Maret',
			4  => 'April',
			5  => 'Mei',
			6  => 'Juni',
			7  => 'Juli',
			8  => 'Agustus',
			9  => 'September',
			10 => 'Oktober',
			11 => 'November',
			12 => 'Desember',
		);

		return sprintf(
			'%d %s %d',
			(int) gmdate( 'j', $stamp ),
			$months[ (int) gmdate( 'n', $stamp ) ],
			(int) gmdate( 'Y', $stamp )
		);
	}

	/**
	 * Express an amount in Indonesian words, as required on a kwitansi.
	 *
	 * @param int    $minor    Amount in minor units.
	 * @param string $currency ISO 4217 currency code.
	 *
	 * @return string Amount written out, or '' for non-rupiah amounts.
	 */
	public function amount_in_words( int $minor, string $currency ): string {
		if ( 'IDR' !== strtoupper( $currency ) ) {
			return '';
		}

		return Terbilang::rupiah( $minor );
	}

	/**
	 * Build a document number in the Indonesian house style.
	 *
	 * Indonesian invoice numbers are conventionally composite rather than a bare
	 * running number: sequence / document code / business code / roman month /
	 * year, for example 001/INV/BW/VII/2026. Auditors and clients both expect to
	 * be able to read the month and year straight off the number.
	 *
	 * @param string $type     Document type key, 'invoice' or 'receipt'.
	 * @param int    $sequence Allocated sequence value.
	 * @param string $date     Document date in Y-m-d form.
	 *
	 * @return string Formatted document number.
	 */
	public function document_number( string $type, int $sequence, string $date ): string {
		$stamp = strtotime( $date );

		if ( false === $stamp ) {
			$stamp = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		}

		$padding = (int) Settings::get( 'number_padding', 3 );
		$padding = max( 1, min( 12, $padding ) );

		$code = strtoupper( trim( (string) Settings::get( 'business_code', '' ) ) );
		$code = '' === $code ? 'BIZ' : $code;

		$document_codes = array(
			'invoice' => 'INV',
			'receipt' => 'KW',
		);

		return sprintf(
			'%s/%s/%s/%s/%s',
			str_pad( (string) $sequence, $padding, '0', STR_PAD_LEFT ),
			$document_codes[ $type ] ?? strtoupper( $type ),
			$code,
			self::roman_month( (int) gmdate( 'n', $stamp ) ),
			gmdate( 'Y', $stamp )
		);
	}

	/**
	 * Stamp duty payable on a document stating the given amount.
	 *
	 * Bea meterai of Rp 10,000 applies to documents stating an amount above
	 * Rp 5,000,000 under UU 10/2020. In practice this catches most kwitansi.
	 *
	 * @param int    $minor    Amount in minor units.
	 * @param string $currency ISO 4217 currency code.
	 *
	 * @return int Duty in minor units. Zero when none applies.
	 */
	public function stamp_duty( int $minor, string $currency ): int {
		if ( 'IDR' !== strtoupper( $currency ) ) {
			return 0;
		}

		return $minor > self::METERAI_THRESHOLD ? self::METERAI_AMOUNT : 0;
	}

	/**
	 * Notes Indonesian documents are expected to carry.
	 *
	 * @return string[] Translated notes.
	 */
	public function document_notes(): array {
		return array(
			__( 'Pembayaran dianggap lunas setelah dana diterima di rekening yang tercantum.', 'wp-bizwit' ),
			__( 'Mohon cantumkan nomor faktur pada berita transfer.', 'wp-bizwit' ),
		);
	}

	/**
	 * Roman numeral for a month, as used in Indonesian document numbers.
	 *
	 * @param int $month Month number, 1 to 12.
	 *
	 * @return string Roman numeral.
	 */
	public static function roman_month( int $month ): string {
		$numerals = array( 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII' );

		return $numerals[ max( 1, min( 12, $month ) ) - 1 ];
	}
}

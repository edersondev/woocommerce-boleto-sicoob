<?php
/*
Plugin Name: WooCommerce Boleto Bancário Sicoob
Description: Boleto bancário Sicoob com registro para woocommerce.
Version: 1.0
Author: Ederson Ferreira <ederson.dev@gmail.com>
Author URI: https://www.linkedin.com/in/edersonfs/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Boleto_Sicoob' ) ) :

	class WC_Boleto_Sicoob {

    /**
    * Instance of this class.
    *
    * @var object
    */
    protected static $instance = null;
  
		/**
		* Initialize the plugin actions.
		*/
		private function __construct() {
			// Checks with WooCommerce is installed.
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				// Public includes.
        $this->includes();
        
        if ( is_admin() ) {
          $this->admin_includes();
        }

        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
		add_action( 'init', array( __CLASS__, 'add_boleto_endpoint' ) );
		add_filter( 'template_include', array( $this, 'boleto_template' ), 9999 );
		add_action( 'woocommerce_view_order', array( $this, 'pending_payment_message' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
		} else {
        add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
      }
		}

		/**
		* Includes.
		*/
		private function includes() {
			include_once 'includes/wc-boleto-functions.php';
			include_once 'includes/class-wc-boleto-sicoob-gateway.php';
    }
    
    /**
    * Includes.
    */
    private function admin_includes() {
      require_once 'includes/class-wc-boleto-admin.php';
    }

    /**
    * Add the gateway to WooCommerce.
    *
    * @param  array $methods WooCommerce payment methods.
    *
    * @return array          Payment methods with Boleto.
    */
    public function add_gateway( $methods ) {
      $methods[] = 'WC_Boleto_Sicoob_Gateway';

      return $methods;
	}
	
	/**
	 * Created the boleto endpoint.
	 */
	 public static function add_boleto_endpoint() {
		add_rewrite_endpoint( 'boleto_sicoob', EP_PERMALINK | EP_ROOT );
	}

	/**
	 * Plugin activate method.
	 */
	 public static function activate() {
		self::add_boleto_endpoint();

		flush_rewrite_rules();
	}

	/**
	 * Add custom template page.
	 *
	 * @param  string $template
	 *
	 * @return string
	 */
	 public function boleto_template( $template ) {
		global $wp_query;

		if ( isset( $wp_query->query_vars['boleto_sicoob'] ) ) {
			$ref = sanitize_title( $wp_query->query_vars['boleto_sicoob'] );
			$order_id = woocommerce_get_order_id_by_order_key( $ref );
			if ( $order_id ) {
				$objOrder = new WC_Order( $order_id );
				$settings = get_option( 'woocommerce_boleto_sicoob_settings' );

				$timestamp = strtotime("+{$settings['boleto_time']} day", strtotime(date('Ymd')));
				$data_vencimento = date('Ymd', $timestamp);
				$cpfCnpj = $this->getCfpCnpj($order_id);
				$cpfCnpjOutput = preg_replace( '/[^0-9]/', '', $cpfCnpj );
				$postcode = $objOrder->data['billing']['postcode'];
				$postcodeOutput = preg_replace( '/[^0-9]/', '', $postcode );
				$params = [
					'numCliente' => $settings['num_cliente'],
					'coopCartao' => $settings['coop_cartao'],
					'chaveAcessoWeb' => $settings['chave_web'],
					'numContaCorrente' => $settings['num_conta'],
					'codMunicipio' => $settings['cod_municipio'],
					'bolRecebeBoletoEletronico' => '1',
					'codEspDocumento' => 'DM',
					'dataEmissao' => date('Ymd'),
					'seuNumero' => $order_id,
					'nomeSacador' => '',
					'numCGCCPFSacador' => '',
					'qntMonetaria' => '',
					'valorTitulo' => $objOrder->get_total(),
					'codTipoVencimento' => '1',
					'dataVencimentoTit' => $data_vencimento,
					'valorAbatimento' => '0',
					'valorIOF' => '0',
					'bolAceite' => '1',
					'percTaxaMulta' => '0',
					'percTaxaMora' => '0',
					'dataPrimDesconto' => '',
					'valorPrimDesconto' => '0',
					'dataSegDesconto' => '',
					'valorSegDesconto' => '0',
					'descInstrucao1' => $settings['boleto_sicoob_inst1'],
					'descInstrucao2' => $settings['boleto_sicoob_inst2'],
					'descInstrucao3' => $settings['boleto_sicoob_inst3'],
					'descInstrucao4' => $settings['boleto_sicoob_inst4'],
					'descInstrucao5' => $settings['boleto_sicoob_inst5'],
					'nomeSacado' => "{$objOrder->data['billing']['first_name']} {$objOrder->data['billing']['last_name']}",
					'cpfCGC' => $cpfCnpjOutput,
					'endereco' => "{$objOrder->data['billing']['address_1']}, {$objOrder->data['billing']['address_2']}",
					'bairro' => get_post_meta( $order_id, '_billing_neighborhood', true ),
					'cidade' => $objOrder->data['billing']['city'],
					'cep' => $postcodeOutput,
					'uf' => $objOrder->data['billing']['state'],
					'telefone' => '',
					'ddd' => '',
					'email' => $objOrder->data['billing']['email']

				];
				//var_dump($params);exit;
				$arrParams = array_map(array($this, 'replaceSemiColon'), $params);
				$fields = '';
				foreach ( $arrParams as $key => $value ) {
					$fields .= "<input name=\"{$key}\" type=\"hidden\" value=\"{$value}\" />\n";
				}
				$html = <<<EOF
				<html>
				<head>
				<meta charset="utf-8">
				<title>Aguarde...</title>
				</head>
				<body onload="document.frm1.submit()">
					<form method="post" action="https://geraboleto.sicoobnet.com.br/geradorBoleto/GerarBoleto.do" name="frm1" accept-charset="ISO-8859-1">
						{$fields}
					</form>
				</body>
				</html>
EOF;
				echo $html;exit;

			}
			exit;
		}

		return $template;
	}

	/**
	 * Substitui o ponto e virgula por espaço vazio
	 */
	private function replaceSemiColon($value)
	{
		return str_replace(';',' ',$value);
	}

	private function getCfpCnpj($order_id)
	{
		$cnpj = get_post_meta( $order_id, '_billing_cnpj', true );
		$cpf = get_post_meta( $order_id, '_billing_cpf', true );
		if (!empty($cpf)) {
			return $cpf;
		}
		return $cnpj;
	}

	/**
	 * Gets the boleto URL.
	 *
	 * @param  string $code Boleto code.
	 *
	 * @return string       Boleto URL.
	 */
	 public static function get_boleto_sicoob_url( $code ) {
		$home = home_url( '/' );

		if ( get_option( 'permalink_structure' ) ) {
			$url = trailingslashit( $home ) . 'boleto_sicoob/' . $code;
		} else {
			$url = add_query_arg( array( 'boleto_sicoob' => $code ), $home );
		}

		return apply_filters( 'woocommerce_boleto_sicoob_url', $url, $code, $home );
	}

	/**
	 * Display pending payment message in order details.
	 *
	 * @param  int $order_id Order id.
	 *
	 * @return string        Message HTML.
	 */
	public function pending_payment_message( $order_id ) {
		$order = new WC_Order( $order_id );

		if ( 'on-hold' === $order->status && 'boleto_sicoob' == $order->payment_method ) {
			$html = '<div class="woocommerce-info">';
			$html .= sprintf( '<a class="button" href="%s" target="_blank" style="display: block !important; visibility: visible !important;">%s</a>', esc_url( wc_boleto_sicoob_get_boleto_url( $order->order_key ) ), 'Pagar Boleto &rarr;' );

			$message = '<strong>Atenção!</strong> Até o momento não esta registrado o pagamento do boleto para este produto.<br />';
			$message .= 'Por favor, clique em PAGAR BOLETO e pague o mesmo pelo seu Internet Banking.<br />';
			$message .= 'Se preferir, imprima e pague em qualquer agência bancária ou casa lotérica.<br />';
			$message .= 'Ignore esta mensagem caso o pagamento já tenha sido realizado.<br />';

			$html .= apply_filters( 'wcboleto_pending_payment_message', $message, $order );

			$html .= '</div>';

			echo $html;
		}
	}

	/**
	 * Action links.
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	 public function plugin_action_links( $links ) {
		$plugin_links = array();

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_boleto_gateway' );
		} else {
			$settings_url = admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Boleto_Gateway' );
		}

		$plugin_links[] = '<a href="' . esc_url( $settings_url ) . '">' . __( 'Settings', 'woocommerce-boleto' ) . '</a>';

		return array_merge( $plugin_links, $links );
	}

	/**
	* Return an instance of this class.
	*
	* @return object A single instance of this class.
	*/
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Get plugin path.
	 *
	 * @return string
	 */
	 public static function get_plugin_path() {
		return plugin_dir_path( __FILE__ );
	}

	}

/**
 * Plugin activation and deactivation methods.
 */
 register_activation_hook( __FILE__, array( 'WC_Boleto_Sicoob', 'activate' ) );
 register_deactivation_hook( __FILE__, array( 'WC_Boleto_Sicoob', 'deactivate' ) );
 
 /**
	* Initialize the plugin.
	*/
 add_action( 'plugins_loaded', array( 'WC_Boleto_Sicoob', 'get_instance' ) );
endif;
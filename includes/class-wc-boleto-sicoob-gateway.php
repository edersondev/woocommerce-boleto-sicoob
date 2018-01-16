<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
* WC Boleto Sicoob Gateway Class.
*
* Built the Boleto Sicoob method.
*/

class WC_Boleto_Sicoob_Gateway extends WC_Payment_Gateway {

  /**
  * Initialize the gateway actions.
  */
  public function __construct()
  {
    $this->id                 = 'boleto_sicoob';
		$this->icon               = apply_filters( 'wcboleto_icon', plugins_url( 'assets/images/boleto.png', plugin_dir_path( __FILE__ ) ) );
		$this->has_fields         = false;
		$this->method_title       = 'Boleto Sicoob';
    $this->method_description = 'Boleto bancário com registro do banco Sicoob';
    
    // Load the settings.
    $this->init_form_fields();
    $this->init_settings();

    // Define user settings variables.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
    $this->boleto_time = $this->get_option( 'boleto_time' );
    
    add_action( 'woocommerce_thankyou_boleto_sicoob', array( $this, 'thankyou_page' ) );
    add_action( 'woocommerce_email_after_order_table', array( $this, 'email_instructions' ), 10, 2 );
    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
  }

  /**
	 * Returns a bool that indicates if currency is amongst the supported ones.
	 *
	 * @return bool
	 */
	protected function using_supported_currency() {
		return ( 'BRL' == get_woocommerce_currency() );
	}

  /**
	 * Admin Panel Options.
	 *
	 * @return string Admin form.
	 */
	public function admin_options() {
		include 'views/html-admin-page.php';
	}

  /**
  * Gateway options.
  */
  public function init_form_fields()
  {
    $shop_name = get_bloginfo( 'name' );

    $this->form_fields = [
      'enabled' => [
        'title'   => 'Ativar/Desativar',
        'type'    => 'checkbox',
        'label'   => 'Ativar Boleto Sicoob',
        'default' => 'yes'
      ],
      'title' => [
        'title' => 'Título',
        'type' => 'text',
        'description' => 'Título que o usuário visualiza durante o checkout',
        'desc_tip' => true,
        'default' => 'Boleto Sicoob'
      ],
      'description' => [
        'title' => 'Descrição',
        'type' => 'textarea',
        'description' => 'Descrição que o usuária visualiza durante do checkout',
        'desc_tip' => true,
        'default' => ''
      ],
      'boleto_details' => [
        'title' => 'Detalhes do boleto',
        'type' => 'title'
      ],
      'boleto_time' => [
        'title' => 'Dias para pagar o boleto',
        'type' => 'text',
        'description' => 'Número de dias para pagar',
        'desc_tip' => true,
        'default' => 5
      ],
      'num_cliente' => [
        'title' => 'Código cliente',
        'type' => 'text'
      ],
      'coop_cartao' => [
        'title' => 'Número do Coop Cartão',
        'type' => 'text'
      ],
      'chave_web' => [
        'title' => 'Chave acesso web',
        'type' => 'text'
      ],
      'num_conta' => [
        'title' => 'Número da conta corrente',
        'type' => 'text'
      ],
      'cod_municipio' => [
        'title' => 'Código do município',
        'type' => 'text'
      ],
      'boleto_instrucao' => [
        'title' => 'Instruções do boleto',
        'type' => 'title'
      ],
      'boleto_sicoob_inst1' => [
        'title' => 'Instrução 1',
        'type' => 'text'
      ],
      'boleto_sicoob_inst2' => [
        'title' => 'Instrução 2',
        'type' => 'text'
      ],
      'boleto_sicoob_inst3' => [
        'title' => 'Instrução 3',
        'type' => 'text'
      ],
      'boleto_sicoob_inst4' => [
        'title' => 'Instrução 4',
        'type' => 'text'
      ],
      'boleto_sicoob_inst5' => [
        'title' => 'Instrução 5',
        'type' => 'text'
      ],
    ];
  }
  
  /**
	 * Process the payment and return the result.
	 *
	 * @param int    $order_id Order ID.
	 *
	 * @return array           Redirect.
	 */
  public function process_payment( $order_id )
  {
    $order = new WC_Order( $order_id );

    // Mark as on-hold (we're awaiting the ticket).
    $order->update_status( 'on-hold', __( 'Awaiting boleto payment', 'woocommerce-boleto' ) );
    
    // Reduce stock levels.
    $order->reduce_order_stock();
    
    if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			WC()->cart->empty_cart();

			$url = $order->get_checkout_order_received_url();
		} else {
			global $woocommerce;

			$woocommerce->cart->empty_cart();

			$url = add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order_id, get_permalink( woocommerce_get_page_id( 'thanks' ) ) ) );
		}

    // Return thankyou redirect.
		return array(
			'result'   => 'success',
			'redirect' => $url
		);
  }

  /**
	 * Output for the order received page.
	 *
	 * @return string Thank You message.
	 */
	public function thankyou_page() {
		$html = '<div class="woocommerce-message">';
		$html .= sprintf( '<a class="btboleto" href="%s" target="_blank" style="display: block !important; visibility: visible !important;">%s</a>', esc_url( wc_boleto_sicoob_get_boleto_url( $_GET['key'] ) ), 'Pagar Boleto &rarr;' );

		$message = $this->messageToCustomer();

		$html .= apply_filters( 'wcboleto_sicoob_thankyou_page_message', $message );

		$html .= '<strong style="display: block; margin-top: 15px; font-size: 0.8em">Boleto válido até: ' . date( 'd/m/Y', time() + ( absint( $this->boleto_time ) * 86400 ) ) . '</strong>';

		$html .= '</div>';

		echo $html;
	}

  /**
	 * Add content to the WC emails.
	 *
	 * @param  object $order         Order object.
	 * @param  bool   $sent_to_admin Send to admin.
	 *
	 * @return string                Billet instructions.
	 */
  function email_instructions( $order, $sent_to_admin )
  {
    if ( $sent_to_admin || 'on-hold' !== $order->status || 'boleto_sicoob' !== $order->payment_method ) {
			return;
		}

		$btStyle = 'background-color: #6dbe14;border: 1px solid #6dbe14;color: #fff;font-weight: normal;padding: 10px 25px;margin-bottom: 10px;';

		$html = '<h2>' . __( 'Payment', 'woocommerce-boleto' ) . '</h2>';

		$html .= '<p class="order_details">';

		$message = $this->messageToCustomer();

		$html .= apply_filters( 'wcboleto_email_instructions', $message );

		$html .= '<br />' . sprintf( '<a style="'.$btStyle.'"  class="button" href="%s" target="_blank">%s</a>', esc_url( wc_boleto_sicoob_get_boleto_url( $order->order_key ) ), 'Pagar Boleto &rarr;' ) . '<br />';

		$html .= '<strong style="font-size: 0.8em">Boleto válido até: ' . date( 'd/m/Y', time() + ( absint( $this->boleto_time ) * 86400 ) ) . '</strong>';

		$html .= '</p>';

		echo $html;
  }

  private function messageToCustomer()
  {
    $message = '<strong>Atenção</strong> Você não receberá o boleto pelos Correios.<br />';
    $message .= 'Por favor, clique em PAGAR BOLETO e pague o mesmo pelo seu Internet Banking.<br />';
    $message .= 'Se preferir, imprima e pague em qualquer agência bancária ou casa lotérica.<br />';
    return $message;
  }

}
import { sprintf, __ } from '@wordpress/i18n';
import Content from'./CurrencyAndNetworkManager';

// Self-invoking function to encapsulate logic and avoid global scope pollution
(function ( React ) {
  // Fetching settings from window object
  const settings = window.wc.wcSettings.getPaymentMethodData( 'cpmw' );
  
  // Component to render payment block label
  const PaymentBlockLabel = () => {
    return(
      <>
        <div key={ settings.title }>{ settings.title }</div>
        <div key="cpmw_logo" style={{ flexGrow: "1", display: 'flex', justifyContent: 'end', paddingRight: '14px', paddingLeft: '14px' }}>
          <img  key={settings.title} title={settings.title} style={{ marginBottom: '2px', width: 'auto', height: '28px' }} src={settings.logo_url} />	
        </div>
      </>
    )
  }
  
  // Registering the payment method with wcBlocksRegistry
  window.wc.wcBlocksRegistry.registerPaymentMethod({
    name: `cpmw`,
    label: Object( window.wp.element.createElement )( PaymentBlockLabel, null ),
    ariaLabel: settings.title,
    content: settings.error?<>{settings.error}</>:<Content />,
    edit: settings.error?<>{settings.error}</>:<Content />,
    placeOrderButtonLabel: settings.order_button_text,
    canMakePayment: () => {  return true }, // This function can be updated to check if the payment method can be used
    paymentMethodId: `cpmw`,
    supports: {
      features: settings.supports		
    }
  })
})( window.React )

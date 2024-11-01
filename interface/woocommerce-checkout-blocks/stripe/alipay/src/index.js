import { decodeEntities } from '@wordpress/html-entities';

const { registerPaymentMethod } = window.wc.wcBlocksRegistry
const { getSetting } = window.wc.wcSettings

const settings = getSetting( 'china_payments_stripe_alipay_data', {} )

const label = decodeEntities( settings.title )

const Content = () => {
  return decodeEntities( settings.description || '' )
}

const Icon = () => {
  return settings.icon
    ? <img src={settings.icon} style={{ float: 'right', marginRight: '20px' }} />
    : ''
}

const Label = () => {
  return (
    <span style={{ width: '100%' }}>
      {label}
      <Icon />
    </span>
  )
}

registerPaymentMethod( {
  name: "china_payments_stripe_alipay",
  label: <Label />,
  content: <Content />,
  edit: <Content />,
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports,
  }
} );
<?php

class MobWeb_OrderNotificationSms_Helper_Data extends Mage_Core_Helper_Abstract {

	public $app_name = 'Order Notification SMS';

	public function getSettings()
	{
		// Create an empty array
		$settings = array();

		// Populate the settings
		$settings[ 'twilio_account_sid' ] = Mage::getStoreConfig( 'ordernotificationsms/twilio_api_credentials/account_sid' );
		$settings[ 'twilio_auth_token' ] = Mage::getStoreConfig( 'ordernotificationsms/twilio_api_credentials/auth_token' );
		$settings[ 'twilio_sender_number' ] = Mage::getStoreConfig( 'ordernotificationsms/twilio_api_credentials/sender_number' );
		$settings[ 'recipients' ] = Mage::getStoreConfig( 'ordernotificationsms/notification_settings/notification_recipients' );

		// Separate the recipients
		$settings[ 'recipients' ] = explode( ';', $settings[ 'recipients' ] );

		// Return the settings
		return $settings;
	}

	public function sendSms( $body )
	{
		// Get the settings
		$settings = $this->getSettings();

		// If no recipient has been set, don't do anything
		if( !count( $settings[ 'recipients' ] ) ) {
			return;
		}

		// Loop through the recipients and send each SMS separately
		$errors = array();
		foreach( $settings[ 'recipients' ] AS $recipient ) {
			// Send the request via CURL
			$ch = curl_init( sprintf( 'https://api.twilio.com/2010-04-01/Accounts/%s/SMS/Messages.xml', $settings[ 'twilio_account_sid' ] ) );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false ); // Skip SSL certificate verification
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_USERPWD, sprintf( '%s:%s', $settings[ 'twilio_account_sid' ], $settings[ 'twilio_auth_token' ] ) );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, array(
				'From' => $settings[ 'twilio_sender_number' ],
				'To' => $recipient,
				'Body' => $body
			));

			// Execute the request
			curl_exec( $ch );
			$response = curl_getinfo( $ch );
			$response_code = $response[ 'http_code' ];

			// Check the response code. If it's not 201, an error has occured
			if( $response_code !== 201 ) {
				$errors[] = array(
					$settings[ 'twilio_account_sid' ],
					$settings[ 'twilio_auth_token' ],
					$settings[ 'twilio_sender_number' ],
					$settings[ 'recipients' ],
					print_r( $response, true )
				);
			}
		}

		// Check if any errors have occured
		if( count( $errors ) ) {
			// Log the errors
			$this->log( 'Unable to send sms via twilio: ' . print_r( $errors, true ) );
			return false;
		} else {
			$this->log( 'sms sent' );
			return true;
		}
	}

	public function sendAdminEmail( $body )
	{
		// Get the email settings from the store
		$store_name = Mage::app()->getStore()->getFrontendName();
		$general_contact_name = Mage::getStoreConfig( 'trans_email/ident_general/name' );
		$general_contact_email = Mage::getStoreConfig( 'trans_email/ident_general/email' );

		// Set the subject
		$subject = sprintf( '%s: Notification from «%s»', $store_name, $this->app_name );

		// Create the mail object
		$mail = Mage::getModel( 'core/email' );
		$mail->setToName( $general_contact_name );
		$mail->setToEmail( $general_contact_email );
		$mail->setBody( $body );
		$mail->setSubject( '=?utf-8?B?' . base64_encode( $subject ) . '?=' );
		$mail->setFromEmail( $general_contact_email );
		$mail->setFromName( $this->app_name );
		$mail->setType( 'text' );

		// Try sending the email
		try {
		    $mail->send();
		    $this->log( 'email sent to admin' );
		}
		catch ( Exception $e ) {
		    Mage::logException( $e );
		    $this->log( 'unable to send email to admin: ' . print_r( $e, true ) );
		}
	}

	public function log( $msg )
	{
		Mage::log( $msg, null, 'mobweb_ordernotificationsms.log', true );
	}
}
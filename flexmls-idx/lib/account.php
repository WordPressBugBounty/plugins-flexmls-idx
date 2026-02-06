<?php

#[\AllowDynamicProperties]
class FMC_Account {

	function __construct($data) {

		if( ! empty( $data ) && is_array( $data ) ){
			foreach ($data as $property => $value) {
				$this->$property = $value;
			}
		}
	}

	function primary_email() {
		if ( ! is_array( $this->Emails ) || count( $this->Emails ) === 0 ) {
			return false;
		}
		foreach ($this->Emails as $email) {
			if ( is_array( $email ) && array_key_exists( 'Primary', $email ) && isset( $email['Address'] ) ) {
				return $email['Address'];
			}
		}
		if ( isset( $this->Emails[0]['Address'] ) ) {
			return $this->Emails[0]['Address'];
		}
		return false;
	}


}

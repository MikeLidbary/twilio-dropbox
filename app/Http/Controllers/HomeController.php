<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\DropboxFile;
use Kunnu\Dropbox\Exceptions\DropboxClientException;
use Twilio\Rest\Client;
use Twilio\TwiML\VoiceResponse;

class HomeController extends Controller {
	/**
	 * TwiML for outbound call
	 * @return $this
	 */
	public function Response() {

		$response = new VoiceResponse();
		$response->say( 'Please leave a message at the beep.Press the star key when finished.' );
		$response->record( [
			'maxLength'                     => 30,
			'finishOnKey'                   => '*',
			'recordingStatusCallback'       => 'BASE_URL/save',
			'recordingStatusCallbackMethod' => 'GET'
		] );
		$response->say( 'I did not receive a recording' );

		return response( $response, 200 )
			->header( 'Content-Type', 'application/xml' );
	}

	/**
	 * make the call
	 */

	public function Makecall() {

		$sid    = getenv('ACCOUNT_SID');
		$token  = getenv('TWILIO_TOKEN');
		$twilio_number=getenv('TWILIO_NUMBER');
		$reciver_number=getenv('RECEIVER_NUMBER');
		$twilio         = new Client( $sid, $token );

		$call = $twilio->calls
			->create( $reciver_number, // the receiver
				$twilio_number, // your voice enabled number from the console
				array( "url" => "BASE_URL/response" ) // path to the outbound TwiML url
			);
	}

	/**
	 * save the twilio recording
	 */
	public function save( Request $request ) {
		$url = $request->RecordingUrl;
		$img = $request->CallSid . "_" . $request->RecordingSid . ".wav";
		file_put_contents( $img, file_get_contents( $url ) );
		$this->upload($img);
		$this->delete($request->RecordingSid,$img);
	}

	/**
	 * upload recording to Dropbox account
	 * @param $file_name
	 */
	public function upload($file_name) {
		//Configure Dropbox Application
		$app = new DropboxApp( getenv('DROBBOX_APP_KEY'), getenv('DROBBOX_APP_SECRET'), getenv('DROBBOX_ACCESS_TOKEN'));
		//Configure Dropbox service
		$dropbox  = new Dropbox( $app );
		$fileName = $file_name;
		$filePath = $file_name;

		try {
			// Create Dropbox File from Path
			$dropboxFile = new DropboxFile( $filePath );

			// Upload the file to Dropbox
			$uploadedFile = $dropbox->upload( $dropboxFile, "/" . $fileName, [ 'autorename' => true ] );

			// File Uploaded
			echo $uploadedFile->getPathDisplay();
		} catch ( DropboxClientException $e ) {
			echo $e->getMessage();
		}
	}

	/**
	 * delete recording and local file
	 * @param $RecordingSid
	 * @param $file_name
	 */
	public function delete($RecordingSid,$file_name){
		// Find your Account Sid and Auth Token at twilio.com/console
		$sid    = getenv('ACCOUNT_SID');
		$token  = getenv('TWILIO_TOKEN');
		$twilio = new Client($sid, $token);
		$twilio->recordings($RecordingSid)
		       ->delete();
		unlink($file_name) or die("Couldn't delete file");
	}
}

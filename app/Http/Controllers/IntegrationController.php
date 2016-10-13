<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use Session;
use App\File;

use SammyK\LaravelFacebookSdk\LaravelFacebookSdk;
use Twitter;
use Storage;
use Paypal;
use MP;

class IntegrationController extends Controller
{

	private $RESPONSE = array(
		'status' => array(
			'code' => 0,
			'message' => ''
			)
		// 'data' => array()
		);


	public function FBlogin(LaravelFacebookSdk $fb)
	{
		// Send an array of permissions to request
		$login_url = $fb->getLoginUrl();

		return Redirect($login_url);
	}

	public function FBcallback(LaravelFacebookSdk $fb)
	{
		// Obtain an access token.
		try {
			$token = $fb->getAccessTokenFromRedirect();
		} catch (Facebook\Exceptions\FacebookSDKException $e) {
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = $e->getMessage();
		}

		// Access token will be null if the user denied the request
		// or if someone just hit this URL outside of the OAuth flow.
		if (! $token) {
		// Get the redirect helper
			$helper = $fb->getRedirectLoginHelper();

			if (! $helper->getError()) {
				abort(403, 'Unauthorized action.');
			}

		// User denied the request
			dd(
				$helper->getError(),
				$helper->getErrorCode(),
				$helper->getErrorReason(),
				$helper->getErrorDescription()
				);
		}

		if (! $token->isLongLived()) {
		// OAuth 2.0 client handler
			$oauth_client = $fb->getOAuth2Client();

		// Extend the access token.
			try {
				$token = $oauth_client->getLongLivedAccessToken($token);
			} catch (Facebook\Exceptions\FacebookSDKException $e) {
				$this->RESPONSE['status']['code'] = 422;
				$this->RESPONSE['status']['message'] = $e->getMessage();
			}
		}

		$fb->setDefaultAccessToken($token);

		// Save for later
		Session::put('fb_user_access_token', (string) $token);

		// Get basic info on the user from Facebook.
		try {
			$response = $fb->get('/me');
		} catch (Facebook\Exceptions\FacebookSDKException $e) {
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = $e->getMessage();
		}

		// dd($response);

		// Convert the response to a `Facebook/GraphNodes/GraphUser` collection
		// $facebook_user = $response->getGraphUser();

		// Create the user if it does not exist or update the existing entry.
		// This will only work if you've added the SyncableGraphNodeTrait to your User model.

		// $user = App\User::createOrUpdateGraphNode($facebook_user);

		// // Log the user into Laravel
		// Auth::login($user);

		return $token;
	}

	public function FBpublishLink(LaravelFacebookSdk $fb)
	{

		if (isset($_POST['token']) && isset($_POST['link']) && isset($_POST['message'])) {
			$linkData = [
			'link' => 'http://localhost',
			'message' => 'User provided message',
			];

			try {
		// Returns a `Facebook\FacebookResponse` object
				$response = $fb->post('/me/feed', $linkData, Session::get('fb_user_access_token'));
			} catch(Facebook\Exceptions\FacebookResponseException $e) {
				echo 'Graph returned an error: ' . $e->getMessage();
				exit;
			} catch(Facebook\Exceptions\FacebookSDKException $e) {
				echo 'Facebook SDK returned an error: ' . $e->getMessage();
				exit;
			}

			// $graphNode = $response->getGraphNode();

			$this->RESPONSE['status']['code'] = 200;
			$this->RESPONSE['status']['message'] = 'Link posted successfuly!';
		} else {
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = 'Missing parameters!';
		}

		return $this->RESPONSE;
	}

	public function FBpublishPhoto(LaravelFacebookSdk $fb)
	{
		if (isset($_POST['token']) && isset($_POST['link']) && isset($_POST['message'])) {
			$data = [
			'message' => $message,
			'source' => $fb->fileToUpload($img),
			];

			try {
			// Returns a `Facebook\FacebookResponse` object
				$response = $fb->post('/me/photos', $data, $token);
			} catch(Facebook\Exceptions\FacebookResponseException $e) {
				echo 'Graph returned an error: ' . $e->getMessage();
				exit;
			} catch(Facebook\Exceptions\FacebookSDKException $e) {
				echo 'Facebook SDK returned an error: ' . $e->getMessage();
				exit;
			}

			// $graphNode = $response->getGraphNode();

			$this->RESPONSE['status']['code'] = 200;
			$this->RESPONSE['status']['message'] = 'Photo posted successfuly!';
		} else {
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = 'Missing parameters!';
		}

		return $this->RESPONSE;
	}

	public function TWlogin()
	{
		$sign_in_twitter = true;
		$force_login = false;

		// Make sure we make this request w/o tokens, overwrite the default values in case of login.
		Twitter::reconfig(['token' => '', 'secret' => '']);
		$token = Twitter::getRequestToken(route('twitter.callback'));

		if (isset($token['oauth_token_secret']))
		{
			$url = Twitter::getAuthorizeURL($token, $sign_in_twitter, $force_login);

			Session::put('oauth_state', 'start');
			Session::put('oauth_request_token', $token['oauth_token']);
			Session::put('oauth_request_token_secret', $token['oauth_token_secret']);

			return Redirect($url);
		}

		return $this->RESPONSE;
	}

	public function TWcallback(Request $request) 
	{
		if (Session::has('oauth_request_token'))
		{
			$request_token = [
			'token'  => Session::get('oauth_request_token'),
			'secret' => Session::get('oauth_request_token_secret'),
			];

			Twitter::reconfig($request_token);

			$oauth_verifier = false;

			if ($request->has('oauth_verifier'))
			{
				$oauth_verifier = $request->oauth_verifier;
			}

			// getAccessToken() will reset the token for you
			$token = Twitter::getAccessToken($oauth_verifier);

			if (!isset($token['oauth_token_secret']))
			{
				return $this->RESPONSE;
			}

			$credentials = Twitter::getCredentials();

			// dd($credentials);

			if (is_object($credentials) && !isset($credentials->error))
			{
			// $credentials contains the Twitter user object with all the info about the user.
			// Add here your own user logic, store profiles, create new users on your tables...you name it!
			// Typically you'll want to store at least, user id, name and access tokens
			// if you want to be able to call the API on behalf of your users.

			// This is also the moment to log in your users if you're using Laravel's Auth class
			// Auth::login($user) should do the trick.

				Session::put('access_token', $token);

				Twitter::reconfig(['token' => $token['oauth_token'], 'secret' => $token['oauth_token_secret']]);

				return $token;
			}

			return $this->RESPONSE;
		}
	}

	public function TWtweet(Request $request)
	{
		$tweet = ['status' => $request->message];

		if ($request->has('token') && $request->has('message')) {

			if ($request->has('file')) {
				// $uploaded_media = Twitter::uploadMedia(['media' => Storage::get($image->route."/".$image->name)]);

				$uploaded_media = Twitter::uploadMedia(['media' => $request->file]);
				$tweet['media_ids'] = $uploaded_media->media_id_string;
			}	

			Twitter::postTweet($tweet);

			$this->RESPONSE['status']['code'] = 200;
			$this->RESPONSE['status']['message'] = 'Tweet posted successfuly!';
		} else {
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = 'Missing parameters!';
		}

		return $this->RESPONSE;
	}

	public function MP_payment(Request $request) 
	{	
		// $items = array (
  //               array (
  //                   "title" => "Test2",
  //                   "quantity" => 1,
  //                   "currency_id" => "VEF",
  //                   "unit_price" => 100
  //               )
  //           );

		// $back_urls = array(
		// 	"success"=> route('back.url',1),
		// 	"pending"=> route('back.url',2),
		// 	"failure"=> route('back.url',3)
		// 	);

		// $data = array('items' => $items, 'back_urls' => $back_urls, 'client_id' => '7383633796764492', 'client_secret' => 'arR2X20hLztNs6oH6Tq4qwdkllPE3HA2');

		// $request = (object) $data;

		if ($request->has('items') && $request->has('back_urls') && $request->has('client_id') && $request->has('client_secret')) {
			
			$mp = new MP ($request->client_id, $request->client_secret);

			$preference_data = array (
				"items" => $request->items,
				"back_urls" => $request->back_urls
				);

			try {
				$preference = $mp->create_preference($preference_data);

				return redirect()->to($preference['response']['init_point']);
			} catch (Exception $e){
				$this->RESPONSE['status']['code'] = 422;
				$this->RESPONSE['status']['message'] = $e->getMessage();
			}
		} else {
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = 'Missing parameters!';
		}

		return $this->RESPONSE;
	}

	public function PP_payment(Request $request)
	{
		// $data = array('client_id' => 'AbqAQeQhqAfj7zxygfUa-aNTHVWdCSNcT8dYzDhbPXMxO2dL-YbyLe8zc7oRlhnlHh55I0uDR5pxViVS', 'client_secret' => 'EPwfKlzhQdriYBJN21n-TMGiYw3Uq9aAcFOwzinX9xCysLAgTP89AsQqcfCj0yL5ZUucclpl0ItI5UNK', 'currency' => 'USD', 'total' => 100, 'description' => 'test2', 'back_urls' => array('return' => route('back.url',1), 'cancel' => route('back.url',2)), 'items' => array(
		// 	array(
		// 		"name"=> "hat",
		// 		"description"=> "Brown color hat",
		// 		"quantity"=> "1",
		// 		"price"=> "50",
		// 		"currency"=> "USD"
		// 		),
		// 	array(
		// 		"name"=> "handbag",
		// 		"description"=> "Black color hand bag",
		// 		"quantity"=> "1",
		// 		"price"=> "50",
		// 		"currency"=> "USD"
		// 		)));

		// $request = (object) $data;
		

		if ($request->has('client_id') && $request->has('client_secret') && $request->has('currency') && $request->has('total') && $request->has('description') && $request->has('back_urls')) {

			$_apiContext = PayPal::ApiContext($request->client_id, $request->client_secret);


			$_apiContext->setConfig(array(
				'mode' => 'sandbox',
				'service.EndPoint' => 'https://api.sandbox.paypal.com',
				'http.ConnectionTimeOut' => 30
				));

			$payer = PayPal::Payer();
			$payer->setPaymentMethod('paypal');

			$items = array();
			foreach ($request->items as $i) {
				$i = (object) $i;
				$item = Paypal::Item();
				$item->setName($i->name)
				->setDescription($i->description)
				->setCurrency($i->currency)
				->setQuantity($i->quantity)
				->setPrice($i->price);

				array_push($items, $item);
			}

			$items_list = PayPal::ItemList();
			$items_list->setItems($items);

			$amount = PayPal:: Amount();
			$amount->setCurrency($request->currency);
			$amount->setTotal($request->total);

			$transaction = PayPal::Transaction();
			$transaction->setAmount($amount);
			$transaction->setDescription($request->description);

			$redirectUrls = PayPal:: RedirectUrls();
			$redirectUrls->setReturnUrl($request->back_urls['return']);
			$redirectUrls->setCancelUrl($request->back_urls['cancel']);

			$payment = PayPal::Payment();
			$payment->setIntent('sale');
			$payment->setPayer($payer);
			$payment->setRedirectUrls($redirectUrls);
			$payment->setTransactions(array($transaction));

			$response = $payment->create($_apiContext);
			$redirectUrl = $response->links[1]->href;

			Session::put('apiContext', $_apiContext);

			return Redirect()->to($redirectUrl);
		} else {
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = 'Missing parameters!';
		}
		return $this->RESPONSE;
	}

	public function PP_confirm($value, Request $request)
	{
		$id = $request->get('paymentId');
		$token = $request->get('token');
		$payer_id = $request->get('PayerID');

		$_apiContext = Session::get('apiContext');

		$payment = PayPal::getById($id, $_apiContext);

		$paymentExecution = PayPal::PaymentExecution();

		$paymentExecution->setPayerId($payer_id);
		$executePayment = $payment->execute($paymentExecution, $_apiContext);

		dd($executePayment);
	}
}
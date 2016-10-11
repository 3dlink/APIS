<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use Session;
use App\File;

use SammyK\LaravelFacebookSdk\LaravelFacebookSdk;
use Twitter;
use Storage;

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

	public function FBcallback(LaravelFacebookSdk $fb){
		// Obtain an access token.
		try {
			$token = $fb->getAccessTokenFromRedirect();
		} catch (Facebook\Exceptions\FacebookSDKException $e) {
			dd($e->getMessage());
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
				dd($e->getMessage());
			}
		}

		$fb->setDefaultAccessToken($token);

		// Save for later
		Session::put('fb_user_access_token', (string) $token);

		// Get basic info on the user from Facebook.
		// try {
		// 	$response = $fb->get('/me?fields=id,name,email');
		// } catch (Facebook\Exceptions\FacebookSDKException $e) {
		// 	dd($e->getMessage());
		// }

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

			$graphNode = $response->getGraphNode();

			dd($graphNode);
		} else {
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = 'There was an error obtaining the necessary parameters!';
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

			$graphNode = $response->getGraphNode();

			dd($graphNode);
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

	public function TWcallback(Request $request) {
		// You should set this route on your Twitter Application settings as the callback
		// https://apps.twitter.com/app/YOUR-APP-ID/settings
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
		if ($request->has('token') && $request->has('message') && $request->has('file')) {

			// $uploaded_media = Twitter::uploadMedia(['media' => Storage::get($image->route."/".$image->name)]);
			$uploaded_media = Twitter::uploadMedia(['media' => $request->file]);

			Twitter::postTweet(['status' => $request->message, 'media_ids' =>   $uploaded_media->media_id_string]);
		} else {
			$this->RESPONSE['status']['code'] = 422;
			$this->RESPONSE['status']['message'] = 'Missing parameters!';
		}

		return $this->RESPONSE;
	}
}
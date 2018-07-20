<?php
/**
 * @file
 * Contains GoogleServiceProvider
 */

namespace zviryatko\GDocHist;

use XdgBaseDir\Xdg;

final class GoogleServiceProvider {
  /**
   * Returns an authorized API client.
   * @return \Google_Client
   *    The authorized client object
   * @throws \Google_Exception
   */
  private function getClient() {
    $client = new \Google_Client();
    $client->setApplicationName('Google Doc History');
    $client->setScopes([
      \Google_Service_Drive::DRIVE_METADATA,
      \Google_Service_Drive::DRIVE_FILE,
    ]);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $xdg = new Xdg();
    $cacheDir = $xdg->getHomeCacheDir();
    $credentialsPath = $cacheDir . '/token.json';
    if (file_exists($credentialsPath)) {
      $accessToken = json_decode(file_get_contents($credentialsPath), TRUE);
    }
    else {
      // Request authorization from the user.
      $authUrl = $client->createAuthUrl();
      printf("Open the following link in your browser:\n%s\n", $authUrl);
      print 'Enter verification code: ';
      $authCode = trim(fgets(STDIN));

      // Exchange authorization code for an access token.
      $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

      // Store the credentials to disk.
      if (!file_exists(dirname($credentialsPath))) {
        mkdir(dirname($credentialsPath), 0700, TRUE);
      }
      file_put_contents($credentialsPath, json_encode($accessToken));
      printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
      $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
      file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
    }
    return $client;
  }

  public function create(): \Google_Service_Drive {
    // Get the API client and construct the service object.
    $client = $this->getClient();
    return new \Google_Service_Drive($client);
  }
}

# Creators PHP Libraries #

PHP libraries for using Creators Syndicate's delivery methods.

## creators_api ##

PHP interface to Creators GET API. Full docs available here: [http://get.creators.com/docs/wiki](http://get.creators.com/docs/wiki)

- `get_features` - Get a list of available features
- `get_feature_details` - Get details on a feature
- `get_releases` - Get a list of releases for a feature
- `download_file` - Download a file
- `download_zip` - Generate and download a release ZIP
- `syn` - SYN the server
- `api_request` - Make an API request
- `ApiException` - API Exception class

Download the latest PHP package here: [http://get.creators.com/api/etc/download/php](http://get.creators.com/api/etc/download/php)

### Dependencies ###

The test file depends on the Simpletest unit tester: [http://www.simpletest.org/](http://www.simpletest.org/)

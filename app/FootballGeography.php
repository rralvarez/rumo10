<?php
declare(strict_types=1);
/** Regiões operacionais = confederações, inclusive filiações transcontinentais. */
final class FootballGeography {
 public static function region(string $country):string {
  foreach(['CONMEBOL'=>'AR BO BR CL CO EC PY PE UY VE','CONCACAF'=>'US CA MX CR HN GT SV NI PA HT JM DO SR GY','UEFA'=>'EN ES DE IT FR PT NL BE TR SC NO DK GR CY RU CZ PL HU HR AT AZ CH LI UA RS IL BG SE FI IE RO','CAF'=>'ZA NG GH KE MA EG DZ TN TZ CD AO CI ZM UG RW CM SN ML ET LY','AFC'=>'SA JP IN CN TH AE AU KR','OFC'=>'NZ'] as $region=>$list)if(in_array($country,explode(' ',$list),true))return $region;
  return 'UNKNOWN:'.$country;
 }
}

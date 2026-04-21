<?php
/**
 * Known Spark MLS identifiers used for feed-specific behavior.
 */
defined( 'ABSPATH' ) or die( 'This plugin requires WordPress' );

abstract class FMC_MlsIds {

  /** Oregon (ORE) — see WP-1020 square footage field */
  const ORE = '20191104230040909159000000';

  /** Richmond / RVA */
  const RVA = '20051230194116769413000000';
}

# FacetWP - P2P

## Before updating

This version is **not compatible** with P2P facets created with previous version of this plugin. You will need to recreate and re-run the indexing process.

## Description

This plugin add two new type of sources for your facets, Posts to Posts (P2P) connections and their metas.

## Installation

### Requirements

* FacetWP 2.0.4 or greater

### Installation

1. Unpack the download-package.
2. Upload the files to the /wp-content/plugins/ directory.
3. Activate the plugin

## Changelog

### 3.0.0 (Sep 1, 2020)

* New filter `facetp2p_p2p_connexions` : Change available connexions for FacetWP P2P
* New filter `facetp2p_p2p_source_name_from` / `facetp2p_p2p_source_name_to` : Change P2P facets's name
* New filter `facetp2p_p2pmeta_source_name` : Change P2P metas facets's name
* Remove use of deprecated column 'facet_source' in sql queries
* Bump minimum version to FacetWP to 3.3.2

### 2.1.2 (Feb 13, 2020)

* Fix indexing issue for facets with P2P / P2P meta source when variations support is enable (resync needed)

### 2.1.1 (Jul 12, 2019)

* Fix Fatal error when a P2P connection between a post type and a user is created

### 2.1.0 (Nov 10, 2017)

* New filter `facetp2p_p2p_index_params` : Change the data generated for a post when indexing a P2P connexion
* New filter `facetp2p_p2pmeta_index_params` : Change the data generated for post when indexing a P2P metas
* Fix some coding standards

### 2.0.0 (Oct 19, 2017)

Complete rewrite of the plugin. Now directly hook into FacetWP sources list instead of adding a custom facet.

* Add support for P2P connections in FacetWP sources
* Add support for P2P connection metas in FacetWP sources

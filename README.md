# FIPAV Volleyball Calendar Scraper

This project retrieves volleyball match calendars and results from the official FIPAV Friuli Venezia Giulia portal.

The goal is to extract match information for a specific team and display it in a custom webpage, which will later be integrated into a Joomla website.

## Project Purpose

The official website:

http://www.fipavfvg.it/application/agency.asp?show=gare

loads match data inside an iframe pointing to:

http://friulivg.portalefipav.net/risultati-classifiche.aspx

This project builds a **server-side proxy** to retrieve and process that data.

## Features

- Fetch match results from the FIPAV portal
- Parse HTML content from the external site
- Extract match calendar and results
- Prepare data for integration in a Joomla module

## Project Structure

# Copilot Instructions for Tour Service Date Selector

## Context

This repository contains a WooCommerce-based WordPress plugin that enables date and time selection on products to make them bookable.

## Required Coding Practices

- Follow WordPress and WooCommerce best practices for architecture, coding style, security, and compatibility.
- Prefer WordPress/WooCommerce hooks, filters, and official APIs over custom or intrusive implementations.
- Maximize compatibility with themes, page builders, and third-party plugins.
- Keep all plugin functions compatible with WooCommerce-based themes (for example, Shoptimizer) and avoid theme-dependent assumptions in frontend/UI behavior.
- Use object-oriented programming (OOP) with clear class responsibilities.
- Keep changes backward-compatible and avoid breaking public behavior unless explicitly requested.

## Security Requirements

- Validate and sanitize all input.
- Escape all output according to output context.
- Use nonce verification and capability checks for privileged actions.
- Follow WooCommerce data handling and cart/order validation best practices.

## Plugin Focus

- Preserve support for bookable behavior via product-level date and time selection.
- Ensure compatibility with simple and variable product flows.
- Use WooCommerce-compatible patterns for cart, checkout, and order meta integration.

## Ownership Metadata

- Author: Imran Bajwa
- Company: INT SERVICES LLC
- Copyright: All rights reserved

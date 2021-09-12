# CBPanel-Invision

# About

Proprietary administration application built upon the [Invision Power Suite v4.0](https://invisioncommunity.com/) 
(IPS / Invision Community) framework with the primary intent being to better bridge the gap between an IPS installation 
and proprietary licensed software. This project also brings the implementation of 
[Stripe Checkout](https://stripe.com/payments/checkout) to IPS, which in the past was not previously available. 
but yeahThis was completed utilizing 
the [Stripe PHP API](https://github.com/stripe/stripe-php/releases).

# Usage

This project is only provided as a reference point for furthering one's knowledge into IPS application development. It
exists as a partially-functional shell and will most likely not work if installation is attempted. Stripe-php 
and IPS's implementation of StripeCheckout must be included in /sources if you desire to reach a functional version.

# Requirements
```
 - PHP (>=7.4)
 - IPS (>=4.4)
 - Stripe-php (>=2.0.0)
```
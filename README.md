# Builder and Template to create a github wiki with phpDocumentor 3

 ![Latest Stable Version](https://img.shields.io/badge/release-v1.0.0-brightgreen.svg)
 [![Donate](https://img.shields.io/static/v1?label=donate&message=PayPal&color=orange)](https://www.paypal.me/SKientzler/5.00EUR)
 ![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.4-8892BF.svg)
 
----------

## Overview

This package contains a PHP CLI script and the needed template to create the class reference for
your PHP repository in the format for a github wiki using **phpDocumentor 3**.

GitHub wiki provides an easy-to-use tool for creating a documentation.
With this package, the work of adding a complete class reference to this documentation is automated with the help of phpDocumentor. 

## Usage
1. The easiest way is to make the githubwiki.phar (contained in the package in the bin directory) available on the system and configure your project.
2. For more control over the creation steps, the template can be used directly with phpDocumentor and the publication on github can be done with git and/or github-dektop. A corresponding bash script is included in the package for this purpose. 

The use and configuration of both methods is explained in detail in the blog that was published for this package.  

## Donation
If you like **githubwiki** please consider donating at **[Paypal](https://www.paypal.me/SKientzler/5.00EUR)**

## Acknowledgments 
- The builder makes use of the package CLICommander from phpClasses.org contributetd by Don Bauer (lordgnu@me.com). 
- Thanks to **Théo FIDRY** for contributing the **[box](https://github.com/box-project/box)** project to easy-create PHAR's 
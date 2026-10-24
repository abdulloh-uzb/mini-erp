# Products have no stored sale price

_Superseded by [ADR-0007](0007-optional-product-sale-price.md): products may now carry a sale price._

Unlike BILLZ, BITO and MoySklad, a product carries no default sale or purchase price. A document line pre-fills from the last executed document of the same kind for that product, and is empty for a product never bought or sold. To catch a mistyped price propagating, sale lines show the average cost and warn when the price is below it (for users allowed to see costs).

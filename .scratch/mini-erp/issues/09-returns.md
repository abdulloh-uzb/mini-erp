# 09: Sales return and supplier return

**What to build:** From an executed sale, a user creates a sales return limited to what is still returnable; the refund reduces the customer's debt first and pays the rest back in cash; the owner executes it. From an executed purchase, the owner creates a supplier return; it reduces supplier debt first and brings the rest back into the register. Spec stories 57–65; decisions Q50, Q51, D10.

**Blocked by:** 05, 07, 08

**Status:** ready-for-agent

- [ ] "Qaytarish yaratish" on executed sales and purchases opens a draft with every source line at 0 and _Sotilgan/Xarid qilingan_, _Qaytarilgan_, _Qaytarish mumkin_ shown; price read-only from the source line; also creatable from the returns list by picking the source document.
- [ ] Quantity per line ≤ source − already returned ("Ko'pi bilan … qaytarish mumkin").
- [ ] Sales return: employee drafts, owner executes; pieces back at the sale line's stored cost (average cost blends); revenue reversed at sale price; refund reduces customer debt first, the rest is a cash-out in the chosen method (default: original sale's method), subject to the balance rule.
- [ ] Supplier return: owner only (hidden from employees); stock check at the location; average cost recalculated per spec (unchanged if on-hand reaches 0); reduces supplier debt first, the rest is cash-in.
- [ ] Domain tests: returnable limit across several returns, refund split, average cost after both return kinds, stock check. HTTP tests: permissions per return kind.

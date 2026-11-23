# Testing Report

No submission or live customer-data modification was performed. Results below distinguish source/live verification from behavior that remains **Not tested**.

| Test ID | Feature | Procedure | Expected result | Actual result | Status |
|---|---|---|---|---|---|
| TC-001 | Live availability | Requested the live homepage with HTTPS | Public homepage loads successfully | HTTP 200 and homepage content loaded | Passed |
| TC-002 | HTTPS/HSTS | Inspected live response headers | HTTPS with HSTS and security headers | HSTS, nosniff, SAMEORIGIN, Referrer-Policy and Permissions-Policy returned | Passed |
| TC-003 | Homepage hero | Compared live rendered homepage to source integration | Hero title, controls and products render | Live homepage displayed the implementation text and products | Verified visually in text response |
| TC-004 | Product catalogue | Inspected live homepage and product links | Public product pages and categories are reachable | Featured products and category links were returned | Source/live verified |
| TC-005 | Custom table implementation | Inspected plugin installer and table allowlist | Nine custom table groups are defined | Nine custom table names and installation SQL found | Passed |
| TC-006 | Foreign keys | Inspected schema | Critical order/payment relationships use declared or logical relationships | Declared custom foreign keys found; WordPress product/user relationships logically identified | Passed source inspection |
| TC-007 | Standard order validation | Inspected order form handler and repository | Required fields, future date and server pricing are enforced | Validation, nonce, submission token and server pricing found | Not runtime tested |
| TC-008 | Custom order validation | Inspected custom-cake handler | Design, date, notice and upload rules enforced | Validation and private upload logic found | Not runtime tested |
| TC-009 | Duplicate order submission | Inspected submission-key logic | Same form submission cannot create duplicate order | Submission key and duplicate handling found | Not runtime tested |
| TC-010 | Payment claim submission | Inspected payment submission function | Payment requires valid reference, phone and amount; pending status is used | Validation and `pending_verification` implementation found | Not tested; no live payment submitted |
| TC-011 | Payment duplicate prevention | Inspected transaction-reference checks | Same transaction cannot be submitted twice | Duplicate checks and unique-index fallback found | Not runtime tested |
| TC-012 | Staff payment decision | Inspected admin guard and payment decide function | Capability and nonce are required; verify/reject is logged | Capability, nonce guard and audit log implementation found | Not runtime tested |
| TC-013 | Manual-payment rule | Searched payment module | No provider API marks payments verified | No payment-provider API call found | Verified source; no transaction tested |
| TC-014 | Customer account | Inspected account handler/templates | Profile and password updates use validation and nonce | Implemented; customer/admin separation found | Not runtime tested |
| TC-015 | Guest tracking | Inspected tracking form and authorized order view | Reference and phone combination locates only the intended order | Implementation and signed token found | Not runtime tested |
| TC-016 | XML-RPC blocked | Inspected `.htaccess` and PHP filters | XML-RPC unavailable | Apache rule and plugin filter found | Not live tested |
| TC-017 | Author enumeration | Inspected template redirect and REST filters | Author lookups and public user endpoint are restricted | Implemented in plugin | Not live tested |
| TC-018 | Private-page cache | Inspected frontend hooks | Order/account/track/contact pages avoid public caching | `DONOTCACHEPAGE` and no-cache headers found | Not runtime tested |
| TC-019 | Wordfence status | Inspected plugin files | Wordfence activation and scan status are known | Plugin source is present; live activation/scan status unverified | Unverified |
| TC-020 | 2FA status | Inspected source and plugin presence | Staff MFA is known | No activation/configuration evidence found | Unverified |
| TC-021 | Backup | Inspected project and plugin source | Backup schedule and restoration are known | UpdraftPlus source present; no dump or restore evidence found | Unverified |
| TC-022 | Database backup availability | Searched project for SQL/ZIP | Restore source is available | No SQL dump or backup archive found | Not available |
| TC-023 | File permissions | Inspected Apache rules only | OS permissions are least-privilege | Permissions could not be verified locally | Unverified |
| TC-024 | Responsive UI | Live text and source CSS available | Layout works on mobile and desktop | Source contains responsive CSS; device test not performed | Not tested |

## Testing limitations

Source inspection cannot prove runtime database or staff behavior. Live testing was limited to safe public HTTP requests. No account credentials, customer records or payment details were used.

## Test Coverage Summary

The tests in this report cover several different levels of verification:

| Verification level | Coverage |
|---|---|
| Public website availability | Live HTTP response inspection |
| Security configuration | Response headers and source inspection |
| Application logic | PHP source-code inspection |
| Database structure | Table definitions and relationship inspection |
| Order and payment processing | Implementation inspected; runtime behavior not tested |
| Customer account functionality | Implementation inspected; runtime behavior not tested |
| Responsive presentation | CSS inspected; physical device testing not performed |

A source-code inspection result must not be interpreted as proof that a feature works correctly in a live transaction.

## Recommended Regression Testing Procedure

After an authorized update to the website, the following checks should be performed in a safe test environment:

1. Confirm that the homepage and main navigation load.
2. Open the product catalogue and confirm that product information is displayed correctly.
3. Open the ordering page and check that the form loads.
4. Validate required fields using safe test information.
5. Confirm that invalid order submissions are rejected.
6. Test order creation using an authorized test environment and test data.
7. Confirm that payment submissions remain pending until staff verification.
8. Test payment verification and rejection using authorized test records.
9. Confirm that customers can access the permitted order-tracking information.
10. Check customer account access and order history.
11. Review relevant PHP and WordPress logs for errors.
12. Check the website on both desktop and mobile screen sizes.

## Test Evidence and Reporting

For future testing sessions:

- Record the test date and environment.
- Identify the feature and procedure tested.
- Record the expected and actual results.
- Assign a clear status: Passed, Failed, Not tested or Unverified.
- Keep screenshots or other evidence where appropriate.
- Do not include customer passwords, payment credentials or private customer information in test evidence.
- Re-test affected features after fixing a reported defect.

## Overall Assessment

The project has documented implementation evidence and successful public availability checks. Several important application functions have been inspected in source code but still require controlled runtime testing before they can be declared fully tested.

This report should be updated whenever additional tests are completed.

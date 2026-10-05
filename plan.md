For Backend we will use: composer create-project jarir/nemesis-framework

For Frontend, use: SvelteKit

For CSS, use: TailwindCSSd

ভাই, Ornaments World-এর website নিয়ে আমার overall concept এবং requirement নিচে দিলাম। Website বানানোর সময় মূল লক্ষ্য হবে—Premium/Luxury presentation + খুব সহজ shopping experience + সহজ order management।

1. Overall Design & Theme

Website-এর পুরো design Black, Golden & White theme-এর উপর হবে।

আমি চাই website দেখলেই যেন একটা premium, luxury men's jewellery brand মনে হয়। Design হবে clean, elegant, modern এবং professional।

- Main color: Black, Golden & White
- Minimal এবং clean layout
- অতিরিক্ত color ব্যবহার করা যাবে না
- Product-এর ছবি যেন সবচেয়ে বেশি highlight হয়
- Premium typography
- Smooth animation/hover effect থাকতে পারে, তবে বেশি animation দিয়ে website heavy করা যাবে না
- Mobile version সবচেয়ে বেশি গুরুত্ব দিয়ে তৈরি করতে হবে
- Website যেন premium brand-এর মতো feel দেয়, কিন্তু unnecessary complicated না হয়

মূল কথা: দেখতে luxury, ব্যবহার করতে extremely simple।

---

2. Homepage

Homepage এমনভাবে সাজাতে হবে যেন একজন নতুন customer website-এ ঢুকেই বুঝতে পারে আমরা কী বিক্রি করি এবং সহজে product দেখতে পারে।

Homepage-এ থাকতে পারে:

- Premium Hero Banner
- “Shop Now” / “Explore Collection” button
- Best Selling Products
- New Arrivals
- Featured Collection
- Product Categories
- Why Choose Ornaments World
- Customer Reviews
- Social Media section
- Footer-এ প্রয়োজনীয় information

Hero section-এ product-এর premium photography এবং short, powerful brand message থাকবে।

---

3. Product Categories

Product category পরিষ্কারভাবে দেখাতে হবে।

যেমন:

- Bracelets
- Cuban Bracelets
- Agate/Akik Stone Bracelets
- Chains
- Rings
- Lockets
- Other Men's Jewellery

পরবর্তীতে নতুন category add করার সুবিধা থাকতে হবে।

---

4. Product Page

প্রতিটি product-এর জন্য আলাদা এবং সুন্দর product page থাকবে।

Product page-এ:

- Multiple high-quality product images
- Product name
- Product price
- Discount/offer থাকলে সেটা
- Product description
- Product specifications
- Available variation/size/color থাকলে সেটা
- Stock availability
- Delivery information
- Customer reviews
- Add to Cart
- Order Now

Product page-এ customer যেন কোনো confusion ছাড়াই product দেখে এবং order করতে পারে।

---

5. Shopping Cart System — খুব সহজ রাখতে হবে

Cart system একদম simple এবং user-friendly হবে।

Customer কোনো product পছন্দ করলে:

Add to Cart → Cart → Checkout → Order Place

এখানে unnecessary steps রাখা যাবে না।

আর customer যদি cart ব্যবহার করতে না চায় এবং সরাসরি product কিনতে চায়, তাহলে:

Order Now → Direct Order Form

এই system থাকবে।

অর্থাৎ customer-এর জন্য দুইটা সহজ option:

Add to Cart
অথবা
Order Now

---

6. Direct Order System

Customer “Order Now” button-এ click করলে সরাসরি একটি simple order form/open checkout page আসবে।

সেখানে প্রয়োজনীয় information:

- Full Name
- Phone Number
- Full Address
- Email Address (optional/available field)
- City/Area
- Product/Quantity
- Delivery charge
- Total amount

এরপর customer শুধু Place Order button-এ click করবে।

আমি চাই checkout process যতটা সম্ভব short এবং simple হোক।

Customer-এর কাছে অপ্রয়োজনীয় information চাওয়া যাবে না।

---

7. Delivery Charge

Website-এ delivery charge automatically calculate/show করতে হবে।

Inside Dhaka: 60 BDT
Outside Dhaka: 120 BDT

Customer checkout করার সময় delivery location অনুযায়ী delivery charge দেখতে পাবে এবং total amount automatically calculate হবে।

Example:

Product Price: ৳500
Delivery Charge: ৳60
Total: ৳560

Outside Dhaka হলে:

Product Price: ৳500
Delivery Charge: ৳120
Total: ৳620

---

8. Order Confirmation Process

Customer website থেকে order করার পর orderটি আমাদের admin panel-এ আসবে।

Order status থাকবে, যেমন:

New Order → Pending Confirmation → Confirmed → Processing → Shipped → Delivered / Cancelled

Customer-এর order পাওয়ার পর আমরা customer-কে ফোন করে order confirm করব।

ফোনে confirmation হওয়ার আগে order সরাসরি delivery/shipping-এর জন্য পাঠানো হবে না।

যে order customer-এর সাথে ফোনে confirm করা হবে, সেটাকে admin panel থেকে Confirmed করা যাবে।

---

9. Pathao Integration — খুব গুরুত্বপূর্ণ

আমার জন্য website-এর সবচেয়ে গুরুত্বপূর্ণ featureগুলোর একটি হলো Pathao Courier integration।

আমরা যেসব confirmed order website থেকে delivery-এর জন্য পাঠাব, সেগুলো যেন website-এর admin panel থেকেই Pathao Courier-এ পাঠানো যায়।

অর্থাৎ admin panel থেকে একটি confirmed order select করে “Send to Pathao” / “Create Shipment” button থাকবে।

এতে order-এর information automatically Pathao-তে চলে যাবে:

- Customer Name
- Customer Phone Number
- Customer Address
- Delivery Area
- Order ID
- Product/Order information
- COD Amount
- প্রয়োজনীয় delivery information

আমার উদ্দেশ্য হলো—একই order-এর customer information আবার হাতে বসিয়ে Pathao app-এ manually input করতে না হয়।

Pathao automation-এর desired workflow:

Website Order → Phone Confirmation → Confirm Order → Send to Pathao → Pathao Shipment Created

যদি Pathao-এর official API/integration ব্যবহার করে এটা করা সম্ভব হয়, তাহলে API integration-এর মাধ্যমে করতে হবে।

---

10. Pathao-তে যেসব Order Automatically যাবে না

কোনো technical issue, API problem, incomplete information বা অন্য কোনো কারণে যদি কোনো order Pathao-তে automatically create না হয়, তাহলে সেই order যেন হারিয়ে না যায়।

Admin panel-এ আলাদা status/section থাকবে:

“Pathao Pending / Manual Shipment Required”

সেখানে এমন orderগুলো দেখা যাবে।

এরপর আমরা manually সেই orderগুলো Pathao-তে create করে দিতে পারব।

অর্থাৎ:

Successful API Order → Automatically Pathao

Failed/Unsuccessful Order → Manual Order List

এটা খুব গুরুত্বপূর্ণ, কারণ কোনো order যেন automation-এর কারণে miss না হয়।

---

11. Admin Dashboard

Admin panel simple কিন্তু powerful হতে হবে।

আমি যেন সহজে দেখতে পারি:

- Total Orders
- New Orders
- Pending Confirmation
- Confirmed Orders
- Pathao Pending
- Shipped Orders
- Delivered Orders
- Cancelled Orders
- Total Sales
- Product Stock

প্রতিটি order-এর details এক জায়গা থেকে দেখা যাবে।

এবং order status manually change করা যাবে।

---

12. Product Management

Admin panel থেকে আমি যেন নিজেই:

- Product add করতে পারি
- Product edit করতে পারি
- Product delete করতে পারি
- Product price change করতে পারি
- Discount দিতে পারি
- Product image upload করতে পারি
- Stock update করতে পারি
- Product category select করতে পারি
- Product variation/size/color add করতে পারি
- Product active/inactive করতে পারি

Developer-এর সাহায্য ছাড়া daily product management করার মতো সহজ interface চাই।

---

13. Customer Experience

Customer-এর জন্য website-এর পুরো process খুব simple রাখতে হবে।

Ideal customer journey:

Website Visit → Product Browse → Product Select → Order Now → Information Fill Up → Place Order

অথবা:

Website Visit → Product Browse → Add to Cart → Checkout → Place Order

Customer যেন unnecessary registration, complicated account creation বা অনেকগুলো page-এর মধ্য দিয়ে যেতে বাধ্য না হয়।

---

14. Mobile First

আমাদের বেশিরভাগ customer Facebook, Instagram, TikTok বা mobile browser থেকে website-এ আসবে।

তাই mobile version অত্যন্ত গুরুত্বপূর্ণ।

Website:

- Android
- iPhone
- Tablet
- Desktop

সব জায়গায় properly responsive হতে হবে।

বিশেষ করে mobile-এ:

- Product image সুন্দরভাবে দেখা যাবে
- Price পরিষ্কার থাকবে
- Add to Cart button visible থাকবে
- Order Now button prominent থাকবে
- Checkout সহজ হবে
- Page loading fast হবে

---

15. Facebook / Meta / Google Tracking

Website future advertising-এর জন্য ready রাখতে হবে।

যাতে পরবর্তীতে সহজে:

- Meta Pixel
- Meta Conversion API
- Google Analytics
- Google Search Console
- Google Ads conversion tracking

ইত্যাদি integrate করা যায়।

বিশেষ করে View Content, Add to Cart, Initiate Checkout এবং Purchase event track করার ব্যবস্থা রাখতে হবে।

---

16. SEO & Performance

Website শুধু সুন্দর হলেই হবে না, fast এবং search-engine friendly হতে হবে।

আমি চাই:

- Fast loading
- Optimized images
- SEO-friendly URL
- Meta title/description
- Product structured data/schema
- Sitemap
- Mobile optimization
- SSL/security
- Clean code
- Future scalability

Website যেন unnecessary plugin/code দিয়ে heavy না হয়ে যায়।

---

17. Future Expansion

এখন মূলত Bangladesh market target করব।

কিন্তু future-এ আমরা international market, বিশেষ করে UK/USA market-এ যেতে পারি।

তাই website architecture এমনভাবে তৈরি করতে হবে যেন ভবিষ্যতে:

- International shipping
- Multiple currency
- International payment gateway
- Different delivery system
- English-focused international storefront

যোগ করা যায়।

এখন এগুলো active করার দরকার নেই, কিন্তু website যেন future expansion-এর জন্য তৈরি থাকে।

---

সবচেয়ে গুরুত্বপূর্ণ বিষয়

Ornaments World-এর website-এর ক্ষেত্রে আমার priority হলো:

1. Premium Look
2. Simple Customer Experience
3. Fast Website
4. Easy Checkout
5. Easy Admin Management
6. Pathao Automation
7. Facebook/TikTok Ads-এর জন্য Conversion Ready

Website দেখতে যেন একটি premium men's jewellery brand মনে হয়, কিন্তু customer order করতে গিয়ে যেন কোনো ধরনের জটিলতার মধ্যে না পড়ে।

আমি চাই website-এর overall feeling হবে:

“Luxury look, simple shopping.”

এটাই Ornaments World website-এর মূল concept।

18. Fake Order Prevention & Customer Information Validation

আমাদের business-এ অনেক সময় fake order আসে। তাই order করার সময় customer information validation-এর বিষয়টি খুব গুরুত্ব দিয়ে রাখতে হবে।

Customer যেন ইচ্ছামতো incomplete বা ভুল information দিয়ে order place করতে না পারে।

Required Information:

Order করার সময় নিচের informationগুলো mandatory রাখতে হবে:

- Full Name — অবশ্যই proper name দিতে হবে
- Valid Mobile Number — অবশ্যই valid/সঠিক mobile number দিতে হবে
- Complete Address — বিস্তারিত ও সঠিক delivery address দিতে হবে
- District — Bangladesh-এর 64টি জেলার মধ্যে থেকে select করতে হবে
- Area/Thana/Upazila — প্রয়োজন অনুযায়ী select/input করার ব্যবস্থা থাকবে
- Email — optional রাখা যেতে পারে

Mobile Number Validation

Customer-এর mobile number mandatory হবে।

Number field-এ validation থাকতে হবে যাতে:

- ভুল format-এর number দেওয়া না যায়
- অপ্রয়োজনীয় character দেওয়া না যায়
- Bangladesh-এর valid mobile number format ব্যবহার করতে হয়

সম্ভব হলে OTP verification system রাখা যেতে পারে। তবে OTP বাধ্যতামূলক করার আগে development cost এবং customer experience বিবেচনা করে সিদ্ধান্ত নেওয়া যাবে।

District Selection

Customer-এর address নেওয়ার ক্ষেত্রে Bangladesh-এর 64টি District-এর একটি dropdown/select option থাকবে।

Customer manually district লিখবে না; বরং list থেকে নিজের district select করবে।

এর ফলে:

- ভুল district কম হবে
- Delivery charge automatically determine করা সহজ হবে
- Order information পরিষ্কার থাকবে
- Courier integration সহজ হবে

Complete Address

শুধু district select করলেই order complete করা যাবে না।

Customer-কে অবশ্যই পূর্ণ delivery address দিতে হবে।

যেমন:

House/Building → Road/Village → Area/Union → Thana/Upazila → District

Address field-এ প্রয়োজনীয় instruction/placeholder থাকবে যাতে customer বুঝতে পারে কী ধরনের address দিতে হবে।

Order Validation

Customer-এর required information পূরণ না হলে Place Order button কাজ করবে না।

যেমন:

❌ Name নেই → Order করা যাবে না
❌ Valid phone number নেই → Order করা যাবে না
❌ District select করা হয়নি → Order করা যাবে না
❌ Complete address নেই → Order করা যাবে না

সব required information সঠিকভাবে পূরণ করার পরেই order submit করা যাবে।

Duplicate / Suspicious Order Detection

একই customer যদি অল্প সময়ের মধ্যে বারবার একই বা ভিন্ন product-এর order করে, তাহলে admin panel-এ সেই orderগুলোকে Suspicious / Duplicate Order হিসেবে identify করার ব্যবস্থা রাখা ভালো।

যেমন:

- একই phone number থেকে multiple orders
- একই address থেকে multiple orders
- অল্প সময়ের মধ্যে repeated orders
- একই customer-এর previous cancelled/fake orders

এগুলো admin panel-এ দেখা গেলে আমরা ফোন করে verify করতে পারব।

Admin Panel-এ Customer Information

প্রতিটি order-এর সঙ্গে পরিষ্কারভাবে দেখা যাবে:

Customer Name
Phone Number
Email
Full Address
District
Area/Thana/Upazila
Order Date & Time
Order Status
Previous Order History (যদি একই number আগে order করে থাকে)

এতে order confirm করার সময় customer যাচাই করা অনেক সহজ হবে।

মূল উদ্দেশ্য

Customer-এর জন্য checkout process সহজ থাকবে, কিন্তু minimum required information অবশ্যই নিতে হবে।

আমাদের লক্ষ্য:

Easy Checkout + Accurate Customer Information + Fake Order Reduction

অর্থাৎ website যেন customer-এর জন্য সহজ হয়, কিন্তু incomplete বা suspicious information দিয়ে order করা কঠিন হয়।
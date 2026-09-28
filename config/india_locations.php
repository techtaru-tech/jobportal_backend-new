<?php

/*
|--------------------------------------------------------------------------
| Indian states / union territories and the cities under each
|--------------------------------------------------------------------------
|
| Drives the candidate's "Current state" -> "Current city" pickers: the city
| list offered is the one under the state picked. The keys are also the
| `options.states` list, so the two can never disagree.
|
| "Cities" are the state's districts — the complete, bounded list Indian
| forms use — plus the well-known cities whose name is not their district's
| (Noida sits in Gautam Buddha Nagar, Kochi in Ernakulam). District names
| follow current official usage (Rajasthan's 41 after the December 2024
| revision, Chhatrapati Sambhajinagar for Aurangabad, and so on).
|
| Sorted and de-duplicated on load, so entries can be added in any order.
*/

$locations = [
    'Andhra Pradesh' => [
        'Alluri Sitharama Raju', 'Anakapalli', 'Anantapur', 'Annamayya', 'Bapatla',
        'Chittoor', 'East Godavari', 'Eluru', 'Guntur', 'Kadapa', 'Kakinada',
        'Konaseema', 'Krishna', 'Kurnool', 'Nandyal', 'Nellore', 'NTR', 'Palnadu',
        'Parvathipuram Manyam', 'Prakasam', 'Sri Sathya Sai', 'Srikakulam',
        'Tirupati', 'Visakhapatnam', 'Vizianagaram', 'West Godavari',
        // Cities
        'Vijayawada', 'Rajahmundry',
    ],
    'Arunachal Pradesh' => [
        'Anjaw', 'Bichom', 'Changlang', 'Dibang Valley', 'East Kameng', 'East Siang',
        'Kamle', 'Keyi Panyor', 'Kra Daadi', 'Kurung Kumey', 'Leparada', 'Lohit',
        'Longding', 'Lower Dibang Valley', 'Lower Siang', 'Lower Subansiri', 'Namsai',
        'Pakke-Kessang', 'Papum Pare', 'Shi Yomi', 'Siang', 'Tawang', 'Tirap',
        'Upper Siang', 'Upper Subansiri', 'West Kameng', 'West Siang',
        // Cities
        'Itanagar',
    ],
    'Assam' => [
        'Bajali', 'Baksa', 'Barpeta', 'Biswanath', 'Bongaigaon', 'Cachar', 'Charaideo',
        'Chirang', 'Darrang', 'Dhemaji', 'Dhubri', 'Dibrugarh', 'Dima Hasao',
        'Goalpara', 'Golaghat', 'Hailakandi', 'Hojai', 'Jorhat', 'Kamrup',
        'Kamrup Metropolitan', 'Karbi Anglong', 'Kokrajhar', 'Lakhimpur', 'Majuli',
        'Morigaon', 'Nagaon', 'Nalbari', 'Sivasagar', 'Sonitpur',
        'South Salmara-Mankachar', 'Sribhumi', 'Tamulpur', 'Tinsukia', 'Udalguri',
        'West Karbi Anglong',
        // Cities
        'Guwahati', 'Silchar', 'Tezpur',
    ],
    'Bihar' => [
        'Araria', 'Arwal', 'Aurangabad', 'Banka', 'Begusarai', 'Bhagalpur', 'Bhojpur',
        'Buxar', 'Darbhanga', 'East Champaran', 'Gaya', 'Gopalganj', 'Jamui',
        'Jehanabad', 'Kaimur', 'Katihar', 'Khagaria', 'Kishanganj', 'Lakhisarai',
        'Madhepura', 'Madhubani', 'Munger', 'Muzaffarpur', 'Nalanda', 'Nawada',
        'Patna', 'Purnia', 'Rohtas', 'Saharsa', 'Samastipur', 'Saran', 'Sheikhpura',
        'Sheohar', 'Sitamarhi', 'Siwan', 'Supaul', 'Vaishali', 'West Champaran',
        // Cities
        'Arrah', 'Bihar Sharif', 'Chhapra', 'Hajipur', 'Motihari',
    ],
    'Chhattisgarh' => [
        'Balod', 'Baloda Bazar', 'Balrampur', 'Bastar', 'Bemetara', 'Bijapur',
        'Bilaspur', 'Dantewada', 'Dhamtari', 'Durg', 'Gariaband',
        'Gaurela-Pendra-Marwahi', 'Janjgir-Champa', 'Jashpur', 'Kabirdham', 'Kanker',
        'Khairagarh-Chhuikhadan-Gandai', 'Kondagaon', 'Korba', 'Korea', 'Mahasamund',
        'Manendragarh-Chirmiri-Bharatpur', 'Mohla-Manpur-Ambagarh Chowki', 'Mungeli',
        'Narayanpur', 'Raigarh', 'Raipur', 'Rajnandgaon', 'Sakti',
        'Sarangarh-Bilaigarh', 'Sukma', 'Surajpur', 'Surguja',
        // Cities
        'Bhilai', 'Ambikapur', 'Jagdalpur',
    ],
    'Goa' => [
        'North Goa', 'South Goa',
        // Cities
        'Panaji', 'Margao', 'Vasco da Gama', 'Mapusa', 'Ponda',
    ],
    'Gujarat' => [
        'Ahmedabad', 'Amreli', 'Anand', 'Aravalli', 'Banaskantha', 'Bharuch',
        'Bhavnagar', 'Botad', 'Chhota Udaipur', 'Dahod', 'Dang', 'Devbhumi Dwarka',
        'Gandhinagar', 'Gir Somnath', 'Jamnagar', 'Junagadh', 'Kheda', 'Kutch',
        'Mahisagar', 'Mehsana', 'Morbi', 'Narmada', 'Navsari', 'Panchmahal', 'Patan',
        'Porbandar', 'Rajkot', 'Sabarkantha', 'Surat', 'Surendranagar', 'Tapi',
        'Vadodara', 'Valsad',
        // Cities
        'Bhuj', 'Nadiad', 'Vapi', 'Godhra', 'Palanpur',
    ],
    'Haryana' => [
        'Ambala', 'Bhiwani', 'Charkhi Dadri', 'Faridabad', 'Fatehabad', 'Gurugram',
        'Hisar', 'Jhajjar', 'Jind', 'Kaithal', 'Karnal', 'Kurukshetra', 'Mahendragarh',
        'Nuh', 'Palwal', 'Panchkula', 'Panipat', 'Rewari', 'Rohtak', 'Sirsa',
        'Sonipat', 'Yamunanagar',
        // Cities
        'Bahadurgarh', 'Narnaul',
    ],
    'Himachal Pradesh' => [
        'Bilaspur', 'Chamba', 'Hamirpur', 'Kangra', 'Kinnaur', 'Kullu',
        'Lahaul and Spiti', 'Mandi', 'Shimla', 'Sirmaur', 'Solan', 'Una',
        // Cities
        'Dharamshala', 'Baddi', 'Nahan', 'Palampur',
    ],
    'Jharkhand' => [
        'Bokaro', 'Chatra', 'Deoghar', 'Dhanbad', 'Dumka', 'East Singhbhum', 'Garhwa',
        'Giridih', 'Godda', 'Gumla', 'Hazaribagh', 'Jamtara', 'Khunti', 'Koderma',
        'Latehar', 'Lohardaga', 'Pakur', 'Palamu', 'Ramgarh', 'Ranchi', 'Sahibganj',
        'Seraikela Kharsawan', 'Simdega', 'West Singhbhum',
        // Cities
        'Jamshedpur', 'Daltonganj',
    ],
    'Karnataka' => [
        'Bagalkot', 'Ballari', 'Belagavi', 'Bengaluru Rural', 'Bengaluru Urban',
        'Bidar', 'Chamarajanagar', 'Chikkaballapur', 'Chikkamagaluru', 'Chitradurga',
        'Dakshina Kannada', 'Davanagere', 'Dharwad', 'Gadag', 'Hassan', 'Haveri',
        'Kalaburagi', 'Kodagu', 'Kolar', 'Koppal', 'Mandya', 'Mysuru', 'Raichur',
        'Ramanagara', 'Shivamogga', 'Tumakuru', 'Udupi', 'Uttara Kannada',
        'Vijayapura', 'Vijayanagara', 'Yadgir',
        // Cities
        'Bengaluru', 'Hubballi', 'Mangaluru', 'Hosapete', 'Karwar',
    ],
    'Kerala' => [
        'Alappuzha', 'Ernakulam', 'Idukki', 'Kannur', 'Kasaragod', 'Kollam', 'Kottayam',
        'Kozhikode', 'Malappuram', 'Palakkad', 'Pathanamthitta', 'Thiruvananthapuram',
        'Thrissur', 'Wayanad',
        // Cities
        'Kochi',
    ],
    'Madhya Pradesh' => [
        'Agar Malwa', 'Alirajpur', 'Anuppur', 'Ashoknagar', 'Balaghat', 'Barwani',
        'Betul', 'Bhind', 'Bhopal', 'Burhanpur', 'Chhatarpur', 'Chhindwara', 'Damoh',
        'Datia', 'Dewas', 'Dhar', 'Dindori', 'Guna', 'Gwalior', 'Harda', 'Indore',
        'Jabalpur', 'Jhabua', 'Katni', 'Khandwa', 'Khargone', 'Maihar', 'Mandla',
        'Mandsaur', 'Mauganj', 'Morena', 'Narmadapuram', 'Narsinghpur', 'Neemuch',
        'Niwari', 'Pandhurna', 'Panna', 'Raisen', 'Rajgarh', 'Ratlam', 'Rewa', 'Sagar',
        'Satna', 'Sehore', 'Seoni', 'Shahdol', 'Shajapur', 'Sheopur', 'Shivpuri',
        'Sidhi', 'Singrauli', 'Tikamgarh', 'Ujjain', 'Umaria', 'Vidisha',
    ],
    'Maharashtra' => [
        'Ahilyanagar', 'Akola', 'Amravati', 'Beed', 'Bhandara', 'Buldhana',
        'Chandrapur', 'Chhatrapati Sambhajinagar', 'Dharashiv', 'Dhule', 'Gadchiroli',
        'Gondia', 'Hingoli', 'Jalgaon', 'Jalna', 'Kolhapur', 'Latur', 'Mumbai City',
        'Mumbai Suburban', 'Nagpur', 'Nanded', 'Nandurbar', 'Nashik', 'Palghar',
        'Parbhani', 'Pune', 'Raigad', 'Ratnagiri', 'Sangli', 'Satara', 'Sindhudurg',
        'Solapur', 'Thane', 'Wardha', 'Washim', 'Yavatmal',
        // Cities
        'Mumbai', 'Navi Mumbai', 'Pimpri-Chinchwad', 'Kalyan-Dombivli', 'Vasai-Virar',
        'Bhiwandi', 'Ichalkaranji',
    ],
    'Manipur' => [
        'Bishnupur', 'Chandel', 'Churachandpur', 'Imphal East', 'Imphal West',
        'Jiribam', 'Kakching', 'Kamjong', 'Kangpokpi', 'Noney', 'Pherzawl', 'Senapati',
        'Tamenglong', 'Tengnoupal', 'Thoubal', 'Ukhrul',
        // Cities
        'Imphal',
    ],
    'Meghalaya' => [
        'East Garo Hills', 'East Jaintia Hills', 'East Khasi Hills',
        'Eastern West Khasi Hills', 'North Garo Hills', 'Ri Bhoi', 'South Garo Hills',
        'South West Garo Hills', 'South West Khasi Hills', 'West Garo Hills',
        'West Jaintia Hills', 'West Khasi Hills',
        // Cities
        'Shillong', 'Tura', 'Jowai',
    ],
    'Mizoram' => [
        'Aizawl', 'Champhai', 'Hnahthial', 'Khawzawl', 'Kolasib', 'Lawngtlai', 'Lunglei',
        'Mamit', 'Saitual', 'Serchhip', 'Siaha',
    ],
    'Nagaland' => [
        'Chumoukedima', 'Dimapur', 'Kiphire', 'Kohima', 'Longleng', 'Meluri',
        'Mokokchung', 'Mon', 'Niuland', 'Noklak', 'Peren', 'Phek', 'Shamator',
        'Tseminyu', 'Tuensang', 'Wokha', 'Zunheboto',
    ],
    'Odisha' => [
        'Angul', 'Balangir', 'Balasore', 'Bargarh', 'Bhadrak', 'Boudh', 'Cuttack',
        'Deogarh', 'Dhenkanal', 'Gajapati', 'Ganjam', 'Jagatsinghpur', 'Jajpur',
        'Jharsuguda', 'Kalahandi', 'Kandhamal', 'Kendrapara', 'Kendujhar', 'Khordha',
        'Koraput', 'Malkangiri', 'Mayurbhanj', 'Nabarangpur', 'Nayagarh', 'Nuapada',
        'Puri', 'Rayagada', 'Sambalpur', 'Subarnapur', 'Sundargarh',
        // Cities
        'Bhubaneswar', 'Berhampur', 'Rourkela',
    ],
    'Punjab' => [
        'Amritsar', 'Barnala', 'Bathinda', 'Faridkot', 'Fatehgarh Sahib', 'Fazilka',
        'Ferozepur', 'Gurdaspur', 'Hoshiarpur', 'Jalandhar', 'Kapurthala', 'Ludhiana',
        'Malerkotla', 'Mansa', 'Moga', 'Mohali', 'Pathankot', 'Patiala', 'Rupnagar',
        'Sangrur', 'Shaheed Bhagat Singh Nagar', 'Sri Muktsar Sahib', 'Tarn Taran',
        // Cities
        'Zirakpur', 'Rajpura', 'Khanna',
    ],
    'Rajasthan' => [
        'Ajmer', 'Alwar', 'Balotra', 'Banswara', 'Baran', 'Barmer', 'Beawar',
        'Bharatpur', 'Bhilwara', 'Bikaner', 'Bundi', 'Chittorgarh', 'Churu', 'Dausa',
        'Deeg', 'Dholpur', 'Didwana-Kuchaman', 'Dungarpur', 'Hanumangarh', 'Jaipur',
        'Jaisalmer', 'Jalore', 'Jhalawar', 'Jhunjhunu', 'Jodhpur', 'Karauli',
        'Khairthal-Tijara', 'Kota', 'Kotputli-Behror', 'Nagaur', 'Pali', 'Phalodi',
        'Pratapgarh', 'Rajsamand', 'Salumbar', 'Sawai Madhopur', 'Sikar', 'Sirohi',
        'Sri Ganganagar', 'Tonk', 'Udaipur',
        // Cities
        'Bhiwadi', 'Kishangarh', 'Makrana', 'Mount Abu', 'Nathdwara',
    ],
    'Sikkim' => [
        'Gangtok', 'Gyalshing', 'Mangan', 'Namchi', 'Pakyong', 'Soreng',
    ],
    'Tamil Nadu' => [
        'Ariyalur', 'Chengalpattu', 'Chennai', 'Coimbatore', 'Cuddalore', 'Dharmapuri',
        'Dindigul', 'Erode', 'Kallakurichi', 'Kanchipuram', 'Kanyakumari', 'Karur',
        'Krishnagiri', 'Madurai', 'Mayiladuthurai', 'Nagapattinam', 'Namakkal',
        'Nilgiris', 'Perambalur', 'Pudukkottai', 'Ramanathapuram', 'Ranipet', 'Salem',
        'Sivaganga', 'Tenkasi', 'Thanjavur', 'Theni', 'Thoothukudi', 'Tiruchirappalli',
        'Tirunelveli', 'Tirupathur', 'Tiruppur', 'Tiruvallur', 'Tiruvannamalai',
        'Tiruvarur', 'Vellore', 'Viluppuram', 'Virudhunagar',
        // Cities
        'Hosur', 'Nagercoil', 'Ooty',
    ],
    'Telangana' => [
        'Adilabad', 'Bhadradri Kothagudem', 'Hanumakonda', 'Hyderabad', 'Jagtial',
        'Jangaon', 'Jayashankar Bhupalpally', 'Jogulamba Gadwal', 'Kamareddy',
        'Karimnagar', 'Khammam', 'Kumuram Bheem Asifabad', 'Mahabubabad',
        'Mahabubnagar', 'Mancherial', 'Medak', 'Medchal-Malkajgiri', 'Mulugu',
        'Nagarkurnool', 'Nalgonda', 'Narayanpet', 'Nirmal', 'Nizamabad', 'Peddapalli',
        'Rajanna Sircilla', 'Ranga Reddy', 'Sangareddy', 'Siddipet', 'Suryapet',
        'Vikarabad', 'Wanaparthy', 'Warangal', 'Yadadri Bhuvanagiri',
        // Cities
        'Secunderabad', 'Ramagundam',
    ],
    'Tripura' => [
        'Dhalai', 'Gomati', 'Khowai', 'North Tripura', 'Sepahijala', 'South Tripura',
        'Unakoti', 'West Tripura',
        // Cities
        'Agartala',
    ],
    'Uttar Pradesh' => [
        'Agra', 'Aligarh', 'Ambedkar Nagar', 'Amethi', 'Amroha', 'Auraiya', 'Ayodhya',
        'Azamgarh', 'Baghpat', 'Bahraich', 'Ballia', 'Balrampur', 'Banda', 'Barabanki',
        'Bareilly', 'Basti', 'Bhadohi', 'Bijnor', 'Budaun', 'Bulandshahr', 'Chandauli',
        'Chitrakoot', 'Deoria', 'Etah', 'Etawah', 'Farrukhabad', 'Fatehpur',
        'Firozabad', 'Gautam Buddha Nagar', 'Ghaziabad', 'Ghazipur', 'Gonda',
        'Gorakhpur', 'Hamirpur', 'Hapur', 'Hardoi', 'Hathras', 'Jalaun', 'Jaunpur',
        'Jhansi', 'Kannauj', 'Kanpur Dehat', 'Kanpur Nagar', 'Kasganj', 'Kaushambi',
        'Kushinagar', 'Lakhimpur Kheri', 'Lalitpur', 'Lucknow', 'Maharajganj',
        'Mahoba', 'Mainpuri', 'Mathura', 'Mau', 'Meerut', 'Mirzapur', 'Moradabad',
        'Muzaffarnagar', 'Pilibhit', 'Pratapgarh', 'Prayagraj', 'Raebareli', 'Rampur',
        'Saharanpur', 'Sambhal', 'Sant Kabir Nagar', 'Shahjahanpur', 'Shamli',
        'Shravasti', 'Siddharthnagar', 'Sitapur', 'Sonbhadra', 'Sultanpur', 'Unnao',
        'Varanasi',
        // Cities
        'Noida', 'Greater Noida', 'Kanpur',
    ],
    'Uttarakhand' => [
        'Almora', 'Bageshwar', 'Chamoli', 'Champawat', 'Dehradun', 'Haridwar',
        'Nainital', 'Pauri Garhwal', 'Pithoragarh', 'Rudraprayag', 'Tehri Garhwal',
        'Udham Singh Nagar', 'Uttarkashi',
        // Cities
        'Haldwani', 'Rishikesh', 'Roorkee', 'Rudrapur', 'Kashipur',
    ],
    'West Bengal' => [
        'Alipurduar', 'Bankura', 'Birbhum', 'Cooch Behar', 'Dakshin Dinajpur',
        'Darjeeling', 'Hooghly', 'Howrah', 'Jalpaiguri', 'Jhargram', 'Kalimpong',
        'Kolkata', 'Malda', 'Murshidabad', 'Nadia', 'North 24 Parganas',
        'Paschim Bardhaman', 'Paschim Medinipur', 'Purba Bardhaman', 'Purba Medinipur',
        'Purulia', 'South 24 Parganas', 'Uttar Dinajpur',
        // Cities
        'Asansol', 'Durgapur', 'Siliguri', 'Kharagpur', 'Haldia',
    ],

    // Union territories
    'Andaman and Nicobar Islands' => [
        'Nicobar', 'North and Middle Andaman', 'South Andaman',
        // Cities
        'Sri Vijaya Puram (Port Blair)',
    ],
    'Chandigarh' => ['Chandigarh'],
    'Dadra and Nagar Haveli and Daman and Diu' => [
        'Dadra and Nagar Haveli', 'Daman', 'Diu',
        // Cities
        'Silvassa',
    ],
    'Delhi' => [
        'Central Delhi', 'East Delhi', 'New Delhi', 'North Delhi', 'North East Delhi',
        'North West Delhi', 'Shahdara', 'South Delhi', 'South East Delhi',
        'South West Delhi', 'West Delhi',
        // Cities
        'Delhi',
    ],
    'Jammu and Kashmir' => [
        'Anantnag', 'Bandipora', 'Baramulla', 'Budgam', 'Doda', 'Ganderbal', 'Jammu',
        'Kathua', 'Kishtwar', 'Kulgam', 'Kupwara', 'Poonch', 'Pulwama', 'Rajouri',
        'Ramban', 'Reasi', 'Samba', 'Shopian', 'Srinagar', 'Udhampur',
    ],
    'Ladakh' => ['Kargil', 'Leh'],
    'Lakshadweep' => ['Lakshadweep', 'Kavaratti'],
    'Puducherry' => ['Karaikal', 'Mahe', 'Puducherry', 'Yanam'],
];

foreach ($locations as $state => $cities) {
    $cities = array_values(array_unique($cities));
    sort($cities, SORT_NATURAL | SORT_FLAG_CASE);
    $locations[$state] = $cities;
}

ksort($locations, SORT_NATURAL | SORT_FLAG_CASE);

return $locations;

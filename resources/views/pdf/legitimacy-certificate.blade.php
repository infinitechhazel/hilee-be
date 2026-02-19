<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">

    {{-- title --}}
    <title>Certificate of Legitimacy - {{ $legitimacy->alias }}</title>

    {{-- font style --}}
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: portrait;
            margin: 10mm;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #000;
            margin: 0;
            padding: 0;
        }

        /* Certificate Container */
        .certificate-container {
            border: 10px double #800000;
            padding: 10px;
            margin: 15px 20px;
            position: relative;
            height: fit-content;
        }

        /* Border */
        .inner-border {
            border: 3px solid #d4af37;
            padding: 30px 10px;
            display: block;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            height: 50%;
            z-index: -1;
            opacity: 0.08;
            display: table;
            /* flex instead of table */
            justify-content: flex-start;
            /* center them together */
            gap: 10px;
            border-collapse: collapse;
            /* control exact spacing */
        }

        .watermark-logo {
            display: table-cell;
            width: 50%;
            vertical-align: middle;
            text-align: center;
            padding: 0;
            /* removes extra inner spacing */
            margin: 0;
        }


        .watermark-logo img {
            flex: 0 0 auto;
            /* don’t stretch */
            text-align: center;
            padding: 0;
            margin: 0;
            width: 400px;
            height: 400px;
            object-fit: contain;
        }

        /* Header with Dual Logos */
        .header-section {
            text-align: center;
            padding-bottom: 10px;
        }

        .header-content {
            display: table;
            width: 100%;
        }

        .logo-left,
        .logo-right {
            display: table-cell;
            width: 25%;
            vertical-align: middle;
            text-align: center;
        }

        .header-text {
            display: table-cell;
            width: 50%;
            vertical-align: middle;
            text-align: center;
        }

        .logo {
            width: 180px;
            height: 180px;
            border-radius: 100%;
            object-fit: cover;
        }

        .logo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 100%;
            background-color: #f0f0f0;
            border: 2px dashed #ccc;
            display: inline-block;
        }

        .organization-name {
            font-size: 24px;
            font-weight: bold;
            color: #800000;
            text-transform: uppercase;
            font-family: cursive;
            letter-spacing: 3px;
            margin-bottom: 5px;
        }

        .organization-subtitle {
            font-size: 16px;
            color: #800000;
            text-transform: uppercase;
            margin: 3px 0;
        }

        .organization-details {
            font-size: 12px;
            color: #333;
            margin: 2px 0;
        }

        /* Certificate Title */
        .certificate-title {
            text-align: center;
            margin: 25px 0 20px 0;
        }

        .certificate-title h1 {
            font-size: 48px;
            font-weight: bold;
            color: #800000;
            text-transform: uppercase;
            letter-spacing: 5px;
            margin-bottom: 10px;
            font-family: cursive;
        }

        .certificate-subtitle {
            font-size: 14px;
            color: #333;
            font-style: italic;
            margin-bottom: 5px;
        }

        /* Main Content */
        .content-section {
            text-align: center;
            margin: 20px 0;
        }

        .certification-text {
            font-size: 14px;
            line-height: 1.8;
            color: #333;
            text-align: center;
            margin: 20px 40px;
        }

        .recipient-name {
            font-family: 'Great Vibes', cursive;
            font-size: 42px;
            color: #000000;
            margin: 15px 0;
            text-transform: uppercase;
            display: inline-block;
            border-bottom: 2px solid #000;
            padding-bottom: 5px;
        }

        .certificate-details {
            font-size: 12px;
            color: #333;
            line-height: 1.6;
            margin: 20px 60px;
            text-align: justify;
        }

        .detail-highlight {
            font-weight: bold;
            color: #800000;
        }

        /* Date Section */
        .date-section {
            text-align: center;
            margin: 25px 0;
            font-size: 13px;
            color: #333;
            font-style: italic;
        }

        /* Signatories Section - Horizontal Layout */
        .signatories-section {
            display: table;
            width: 100%;
            margin: 40px auto 20px auto;
            table-layout: fixed;
        }

        .signatory-box {
            display: table-cell;
            width: 33.33%;
            vertical-align: bottom;
            text-align: center;
            padding: 0 15px;
        }

        .signature-image {
            display: block;
            max-height: 90px;
            max-width: 130px;
            margin: 0 auto 5px auto;
        }

        .signatory-name {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 5px;
            margin-bottom: 3px;
        }

        .signature-line {
            border-top: 2px solid #000;
            width: 160px;
            margin: 3px auto;
        }

        .signatory-role {
            font-size: 9px;
            font-style: italic;
            color: #666;
            margin-top: 2px;
        }

        /* Footer */
        .certificate-footer {
            text-align: center;
            font-size: 9px;
            color: #666;
            border-top: 3px solid #800000;
            padding-top: 10px;
            margin-top: 20px;
        }

        .footer-line {
            margin-top: 4px;
        }
    </style>
</head>

<body>

    {{-- Watermark with dynamic logos --}}
    <div class="watermark">
        <div class="watermark-logo">
            @if($logoExists)
                <img src="{{ $logoPath }}" alt="Watermark Left">
            @endif
        </div>
        <div class="watermark-logo">
            @if($logoExists2)
                <img src="{{ $logoPath2 }}" alt="Watermark Right">
            @endif
        </div>
    </div>

    <div class="certificate-container">
        <div class="inner-border">

            <!-- Header with Dual Logos -->
            <div class="header-section">
                <div class="header-content">
                    <!-- Left Logo (logo_url) -->
                    <div class="logo-left">
                        @if($logoExists)
                            <img src="{{ $logoPath }}" alt="Logo Left" class="logo">
                        @else
                            <div class="logo-placeholder"></div>
                        @endif
                    </div>

                    <!-- Center Text -->
                    <div class="header-text">
                        <div class="organization-name">{{ $legitimacy->chapter }}</div>
                        <div class="organization-subtitle">{{ strtoupper($legitimacy->alias) }}</div>
                        @if($schoolName)
                            <div class="organization-details">{{ $schoolName }}</div>
                        @endif
                        @if($address)
                            <div class="organization-details">{{ $address }}</div>
                        @endif
                    </div>

                    <!-- Right Logo (logo_url_2) -->
                    <div class="logo-right">
                        @if($logoExists2)
                            <img src="{{ $logoPath2 }}" alt="Logo Right" class="logo">
                        @else
                            <div class="logo-placeholder"></div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="content-section">
                <!-- Title -->
                <div class="certificate-title">
                    <h1>Certificate of Legitimacy</h1>
                    <div class="certificate-subtitle">Official Documentation of Membership</div>
                </div>

                <!-- Certification Statement -->
                <div class="certification-text">
                    This is to certify that
                </div>

                <div class="recipient-name">{{ strtoupper($user->name) }}</div>

                <div class="certificate-details">
                    @if($certificationDetails)
                        {{ $certificationDetails }}
                    @else
                        is a legitimate member of the {{ $legitimacy->chapter }}, holding the position of
                        <span class="detail-highlight">{{ $legitimacy->position }}</span>. This certificate
                        confirms their active membership and good standing within the organization.
                    @endif
                </div>

                <!-- Date Section -->
                <div class="date-section">
                    Given this {{ \Carbon\Carbon::parse($certificateDate)->format('jS') }} day of
                    {{ \Carbon\Carbon::parse($certificateDate)->format('F') }},
                    {{ \Carbon\Carbon::parse($certificateDate)->format('Y') }}
                    @if($schoolName && $legitimacy->school_name)
                        at {{ $legitimacy->school_name }}, {{ $legitimacy->address }}
                    @else
                        at University of Perpetual Help System DALTA, Las Piñas City
                    @endif.
                </div>

                <!-- Signatories - Horizontal Layout -->
                @if($signatories && count($signatories) > 0)
                    <div class="signatories-section">
                        @foreach($signatories as $signatory)
                            <div class="signatory-box">
                                @if($signatory->signature_url && file_exists(public_path($signatory->signature_url)))
                                    <img src="{{ public_path($signatory->signature_url) }}" class="signature-image" alt="Signature">
                                @endif
                                <div class="signatory-name">{{ strtoupper($signatory->name) }}</div>
                                <div class="signature-line"></div>
                                @if($signatory->role)
                                    <div class="signatory-role">{{ $signatory->role }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>

            <!-- Footer -->
            <div class="certificate-footer">
                <div class="footer-line">Issued on {{ \Carbon\Carbon::parse($certificateDate)->format('F j, Y') }}</div>
                <div class="footer-line">© {{ date('Y') }} {{ strtoupper($legitimacy->alias) }}. All rights reserved.
                </div>
            </div>
        </div>
    </div>
</body>

</html>
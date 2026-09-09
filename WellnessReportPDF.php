<?php
// includes/WellnessReportPDF.php
require_once __DIR__ . '/fpdf/fpdf.php';

class WellnessReportPDF extends FPDF {
    protected $student_name;
    protected $student_id_num;
    protected $student_email;
    protected $counselor_name;
    protected $generated_date;
    
    // Set metadata for headers
    public function setReportMeta($student_name, $student_id_num, $student_email, $counselor_name, $generated_date) {
        $this->student_name = $student_name;
        $this->student_id_num = $student_id_num;
        $this->student_email = $student_email;
        $this->counselor_name = $counselor_name ? $counselor_name : 'Unassigned';
        $this->generated_date = $generated_date;
    }
    
    // Page Header
    function Header() {
        if ($this->PageNo() == 1) {
            // First page header banner - Black
            $this->SetFillColor(0, 0, 0);
            $this->Rect(0, 0, 210, 32, 'F');
            
            // Branding Logo symbol (vector drawn medical/wellness cross + shield) - White
            $this->SetDrawColor(255, 255, 255);
            $this->SetFillColor(255, 255, 255);
            $this->SetLineWidth(0.8);
            
            // Draw a small decorative icon/shield
            $this->Rect(15, 6, 12, 12, 'FD');
            $this->SetFillColor(0, 0, 0);
            // vertical line of cross
            $this->Rect(20.2, 8, 1.6, 8, 'F');
            // horizontal line of cross
            $this->Rect(17, 11.2, 8, 1.6, 'F');
            
            // Branding Title
            $this->SetTextColor(255, 255, 255);
            $this->SetFont('Arial', 'B', 15);
            $this->SetXY(32, 6);
            $this->Cell(100, 6, 'MINDCARE HUB', 0, 0, 'L');
            
            $this->SetFont('Arial', '', 8);
            $this->SetXY(32, 12);
            $this->Cell(100, 4, 'Student Mental Wellness & Counseling System', 0, 0, 'L');
            
            // Right Side Header Metadata
            $this->SetFont('Arial', 'B', 11);
            $this->SetXY(120, 7);
            $this->Cell(75, 5, 'STUDENT WELLNESS REPORT', 0, 0, 'R');
            
            $this->SetFont('Arial', '', 8);
            $this->SetXY(120, 13);
            $this->Cell(75, 4, 'Generated on: ' . $this->generated_date, 0, 0, 'R');
            
            // Reset state
            $this->SetTextColor(0, 0, 0);
            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(0.2);
            $this->SetY(38);
        } else {
            // Header for subsequent pages
            $this->SetFillColor(245, 245, 245);
            $this->Rect(0, 0, 210, 16, 'F');
            
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Arial', 'B', 8);
            $this->SetXY(15, 4);
            $this->Cell(100, 4, 'MINDCARE HUB - STUDENT WELLNESS REPORT', 0, 0, 'L');
            
            $this->SetFont('Arial', '', 8);
            $this->SetXY(120, 4);
            $this->Cell(75, 4, 'Student: ' . $this->student_name . ' (' . $this->student_id_num . ')', 0, 0, 'R');
            
            $this->SetDrawColor(0, 0, 0);
            $this->Line(15, 10, 195, 10);
            
            $this->SetY(16);
        }
    }
    
    // Page Footer
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7.5);
        $this->SetTextColor(80, 80, 80);
        $this->SetDrawColor(0, 0, 0);
        $this->Line(15, $this->GetY() - 2, 195, $this->GetY() - 2);
        
        $this->Cell(120, 5, 'CONFIDENTIAL - Authorized counselor and administrative use only.', 0, 0, 'L');
        $this->Cell(60, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }
    
    // Check if new section fits on the page, else add page
    public function ensureSpace($needed_height) {
        if ($this->GetY() + $needed_height > 265) {
            $this->AddPage();
            return true;
        }
        return false;
    }
    
    // Ellipse and Circle support for graphics plotting
    function Circle($x, $y, $r, $style='D') {
        $this->Ellipse($x, $y, $r, $r, $style);
    }
    
    function Ellipse($x, $y, $rx, $ry, $style='D') {
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $lx=4/3*(M_SQRT2-1)*$rx;
        $ly=4/3*(M_SQRT2-1)*$ry;
        $k=$this->k;
        $h=$this->h;
        $this->_out(sprintf('%.2F %.2F m',($x+$rx)*$k,($h-$y)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',($x+$rx)*$k,($h-($y-$ly))*$k,($x+$lx)*$k,($h-($y-$ry))*$k,$x*$k,($h-($y-$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',($x-$lx)*$k,($h-($y-$ry))*$k,($x-$rx)*$k,($h-($y-$ly))*$k,($x-$rx)*$k,($h-$y)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',($x-$rx)*$k,($h-($y+$ly))*$k,($x-$lx)*$k,($h-($y+$ry))*$k,$x*$k,($h-($y+$ry))*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',($x+$lx)*$k,($h-($y+$ry))*$k,($x+$rx)*$k,($h-($y-$ly))*$k,($x+$rx)*$k,($h-$y)*$k));
        $this->_out($op);
    }

    // Section Header component - Black & White
    public function renderSectionHeader($title, $icon = '') {
        $this->Ln(4);
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, strtoupper($title), 0, 1, 'L');
        
        // Draw black underline under section title
        $y = $this->GetY() - 0.5;
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.8);
        $this->Line(15, $y, 65, $y);
        
        $this->SetDrawColor(180, 180, 180);
        $this->SetLineWidth(0.2);
        $this->Line(65, $y, 195, $y);
        $this->Ln(3.5);
    }

    // Render Student Information card - Black & White
    public function renderStudentInfoCard() {
        $this->ensureSpace(35);
        
        // Card Container Box
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
        $this->Rect(15, $this->GetY(), 180, 28, 'DF');
        
        $this->SetTextColor(0, 0, 0);
        $startY = $this->GetY() + 4;
        
        // Column 1
        $this->SetFont('Arial', 'B', 9);
        $this->SetXY(18, $startY);
        $this->Cell(30, 5, 'Student Name:', 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(60, 5, $this->student_name, 0, 0, 'L');
        
        // Column 2
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 5, 'Student ID:', 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(50, 5, $this->student_id_num, 0, 1, 'L');
        
        // Line 2
        $this->SetXY(18, $startY + 6);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 5, 'Email Address:', 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(60, 5, $this->student_email, 0, 0, 'L');
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 5, 'Counselor Name:', 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(50, 5, $this->counselor_name, 0, 1, 'L');
        
        // Line 3 (Date of report)
        $this->SetXY(18, $startY + 12);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 5, 'Report Type:', 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(60, 5, 'Individual Clinical Wellness Summary', 0, 0, 'L');
        
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(30, 5, 'Classification:', 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(50, 5, 'RESTRICTED / MEDICAL LOG', 0, 1, 'L');
        
        $this->SetY($startY + 22);
    }
    
    // Draw Vector Line Chart - Black & White
    public function DrawLineChart($x, $y, $w, $h, $title, $labels, $data, $y_min, $y_max, $y_ticks = [], $line_color = [0, 0, 0]) {
        $this->ensureSpace($h + 10);
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($x, $y - 6);
        $this->Cell($w, 5, $title, 0, 0, 'C');
        
        // Outer box background
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
        $this->Rect($x, $y, $w, $h, 'DF');
        
        // Dimensions variables
        $padding_l = 18;
        $padding_r = 10;
        $padding_t = 8;
        $padding_b = 8;
        
        $plot_x = $x + $padding_l;
        $plot_y = $y + $padding_t;
        $plot_w = $w - ($padding_l + $padding_r);
        $plot_h = $h - ($padding_t + $padding_b);
        
        // Draw grid Y-axes and horizontal lines
        $this->SetDrawColor(220, 220, 220);
        $this->SetLineWidth(0.2);
        
        $tick_count = count($y_ticks);
        foreach ($y_ticks as $val => $label) {
            $pct = ($val - $y_min) / ($y_max - $y_min);
            $gy = $plot_y + $plot_h - ($pct * $plot_h);
            
            // Draw grid line
            $this->Line($plot_x, $gy, $plot_x + $plot_w, $gy);
            
            // Print Y label
            $this->SetTextColor(60, 60, 60);
            $this->SetFont('Arial', '', 7);
            $this->SetXY($x + 1, $gy - 2.5);
            $this->Cell($padding_l - 2, 5, $label, 0, 0, 'R');
        }
        
        $count = count($data);
        if ($count <= 0) {
            // No data placeholder text
            $this->SetTextColor(100, 100, 100);
            $this->SetFont('Arial', 'I', 8);
            $this->SetXY($plot_x, $plot_y + ($plot_h/2) - 3);
            $this->Cell($plot_w, 6, 'No historical assessment logs found', 0, 0, 'C');
            return;
        }
        
        // X scale
        $x_step = ($count > 1) ? ($plot_w / ($count - 1)) : $plot_w;
        
        // Plot points and connect them
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $px = $plot_x + ($i * $x_step);
            $val = floatval($data[$i]);
            $pct = ($val - $y_min) / ($y_max - $y_min);
            $py = $plot_y + $plot_h - ($pct * $plot_h);
            $points[] = [$px, $py, $val];
        }
        
        // Draw line trace - Black
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.7);
        for ($i = 0; $i < count($points) - 1; $i++) {
            $this->Line($points[$i][0], $points[$i][1], $points[$i+1][0], $points[$i+1][1]);
        }
        
        // Draw markers and labels
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.4);
        
        for ($i = 0; $i < count($points); $i++) {
            $px = $points[$i][0];
            $py = $points[$i][1];
            $val = $points[$i][2];
            
            // Draw a small dot circle
            $this->Circle($px, $py, 1.2, 'DF');
            
            // Draw value labels optionally on markers
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Arial', 'B', 6);
            $this->SetXY($px - 4, $py - 4.5);
            $this->Cell(8, 4, $val, 0, 0, 'C');
            
            // Draw X label (e.g. date)
            if (isset($labels[$i])) {
                $this->SetTextColor(80, 80, 80);
                $this->SetFont('Arial', '', 6);
                $this->SetXY($px - 8, $plot_y + $plot_h + 1);
                $this->Cell(16, 4, $labels[$i], 0, 0, 'C');
            }
        }
        
        // Reset state
        $this->SetDrawColor(0,0,0);
        $this->SetLineWidth(0.2);
    }
    
    // Draw Mood Distribution Bar Chart - Black & White Grayscale
    public function DrawMoodDistributionBarChart($x, $y, $w, $h, $mood_counts) {
        $this->ensureSpace($h + 10);
        $this->SetFont('Arial', 'B', 8.5);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY($x, $y - 6);
        $this->Cell($w, 5, 'MOOD DISTRIBUTION FREQUENCY', 0, 0, 'C');
        
        // Outer Box
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(0, 0, 0);
        $this->Rect($x, $y, $w, $h, 'DF');
        
        $mood_colors = [
            'happy' => [80, 80, 80],       // Dark Gray
            'neutral' => [140, 140, 140],  // Medium Gray
            'sad' => [190, 190, 190],      // Light Gray
            'stressed' => [0, 0, 0]        // Pure Black
        ];
        
        $keys = ['happy', 'neutral', 'sad', 'stressed'];
        $max_count = max(array_values($mood_counts));
        if ($max_count <= 0) $max_count = 1;
        
        $padding_t = 6;
        $row_h = ($h - $padding_t * 2) / 4;
        
        for ($i = 0; $i < count($keys); $i++) {
            $key = $keys[$i];
            $count = isset($mood_counts[$key]) ? $mood_counts[$key] : 0;
            $ry = $y + $padding_t + ($i * $row_h) + 1;
            
            // Mood label
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Arial', 'B', 7);
            $this->SetXY($x + 2, $ry + ($row_h/2) - 3.5);
            $this->Cell(18, 5, ucfirst($key), 0, 0, 'L');
            
            // Bar background slot
            $this->SetFillColor(240, 240, 240);
            $this->Rect($x + 21, $ry + 1, $w - 32, $row_h - 4, 'F');
            
            // Actual colored bar (grayscale)
            $bar_w_max = $w - 32;
            $bar_w = ($count / $max_count) * $bar_w_max;
            
            if ($bar_w > 0) {
                $color = $mood_colors[$key];
                $this->SetFillColor($color[0], $color[1], $color[2]);
                $this->Rect($x + 21, $ry + 1, $bar_w, $row_h - 4, 'F');
            }
            
            // Count text at end of bar
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Arial', 'B', 7.5);
            $this->SetXY($x + 21 + $bar_w_max + 1, $ry + ($row_h/2) - 3.5);
            $this->Cell(8, 5, $count, 0, 0, 'L');
        }
    }
}

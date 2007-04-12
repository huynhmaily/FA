<?php

$page_security = 2;
// ----------------------------------------------------------------
// $ Revision:	2.0 $
// Creator:	Joe Hunt
// date_:	2005-05-19
// Title:	Print Invoices
// ----------------------------------------------------------------
$path_to_root="../";

include_once($path_to_root . "includes/session.inc");
include_once($path_to_root . "includes/date_functions.inc");
include_once($path_to_root . "includes/data_checks.inc");
include_once($path_to_root . "sales/includes/sales_db.inc");

//----------------------------------------------------------------------------------------------------

// trial_inquiry_controls();
print_sales_orders();

//----------------------------------------------------------------------------------------------------

function get_sales_order_details($order_no)
{
	$sql = "SELECT stk_code, unit_price, ".TB_PREF."sales_order_details.description,
		".TB_PREF."sales_order_details.quantity, discount_percent, 
		qty_invoiced, 
		".TB_PREF."stock_master.material_cost + ".TB_PREF."stock_master.labour_cost + ".TB_PREF."stock_master.overhead_cost AS standard_cost
		FROM ".TB_PREF."sales_order_details, ".TB_PREF."stock_master 
			WHERE ".TB_PREF."sales_order_details.stk_code = ".TB_PREF."stock_master.stock_id 
				AND order_no =" . $order_no;
	return db_query($sql, "Retreive order Line Items");
}

function print_sales_orders()
{
	global $path_to_root;
	
	include_once($path_to_root . "reporting/includes/pdf_report.inc");
	
	$from = $_POST['PARAM_0'];
	$to = $_POST['PARAM_1'];
	$currency = $_POST['PARAM_2'];
	$bankaccount = $_POST['PARAM_3'];
	$email = $_POST['PARAM_4'];	
	$comments = $_POST['PARAM_5'];

	if ($from == null)
		$from = 0;
	if ($to == null)
		$to = 0;
	$dec =user_price_dec();
	
	$cols = array(4, 60, 225, 300, 325, 385, 450, 515);

	// $headers in doctext.inc	
	$aligns = array('left',	'left',	'right', 'left', 'right', 'right', 'right');
	
	$params = array('comments' => $comments,
					'bankaccount' => $bankaccount);
	
	$baccount = get_bank_account($params['bankaccount']);
	$cur = get_company_Pref('curr_default');
	
	if ($email == 0)
	{
		$rep = new FrontReport(_('SALES ORDER'), "SalesOrderBulk.pdf", user_pagesize());
		$rep->currency = $cur;
		$rep->Font();
		$rep->Info($params, $cols, null, $aligns);
	}

	for ($i = $from; $i <= $to; $i++)
	{
		$myrow = get_sales_order($i);
		$branch = get_branch($myrow["branch_code"]);
		if ($email == 1)
		{
			$rep = new FrontReport("", "", user_pagesize());
			$rep->currency = $cur;
			$rep->Font();
			$rep->title = _('SALES_ORDER');
			$rep->filename = "SalesOrder" . $i . ".pdf";
			$rep->Info($params, $cols, null, $aligns);
		}
		else
			$rep->title = _('SALES ORDER');
		$rep->Header2($myrow, $branch, $myrow, $baccount, 9);

		$result = get_sales_order_details($i);
		$SubTotal = 0;
		while ($myrow2=db_fetch($result))
		{
			$Net = ((1 - $myrow2["discount_percent"]) * $myrow2["unit_price"] * $myrow2["quantity"]);
			$SubTotal += $Net;
			$DisplayPrice = number_format2($myrow2["unit_price"],$dec);
			$DisplayQty = number_format2($myrow2["quantity"],user_qty_dec());
			$DisplayNet = number_format2($Net,$dec);
			if ($myrow2["discount_percent"]==0)
				$DisplayDiscount ="";
			else 
				$DisplayDiscount = number_format2($myrow2["discount_percent"]*100,user_percent_dec()) . "%";
			$rep->TextCol(0, 1,	$myrow2['stk_code'], -2);
			$rep->TextCol(1, 2,	$myrow2['description'], -2);
			$rep->TextCol(2, 3,	$DisplayQty, -2);
			$rep->TextCol(3, 4,	$myrow2['units'], -2);
			$rep->TextCol(4, 5,	$DisplayPrice, -2);
			$rep->TextCol(5, 6,	$DisplayDiscount, -2);
			$rep->TextCol(6, 7,	$DisplayNet, -2);
			$rep->NewLine(1);
			if ($rep->row < $rep->bottomMargin + (15 * $rep->lineHeight)) 
				$rep->Header2($myrow, $branch, $sales_order, $baccount);
		}
		if ($myrow['comments'] != "")
		{
			$rep->NewLine();
			$rep->TextColLines(1, 5, $myrow['comments'], -2);
		}	
		$DisplaySubTot = number_format2($SubTotal,$dec);
		$DisplayFreight = number_format2($myrow["freight_cost"],$dec);

		$rep->row = $rep->bottomMargin + (15 * $rep->lineHeight);
		$linetype = true;
		$doctype = 9;
		if ($rep->currency != $myrow['curr_code'])
		{
			include($path_to_root . "reporting/includes/doctext2.inc");			
		}	
		else
		{
			include($path_to_root . "reporting/includes/doctext.inc");			
		}	

		$rep->TextCol(3, 6, $doc_Sub_total, -2);
		$rep->TextCol(6, 7,	$DisplaySubTot, -2);
		$rep->NewLine();
		$rep->TextCol(3, 6, $doc_Shipping, -2);
		$rep->TextCol(6, 7,	$DisplayFreight, -2);
		$rep->NewLine();
		$DisplayTotal = number_format2($myrow["freight_cost"] + $SubTotal, $dec);
		$rep->Font('bold');	
		$rep->TextCol(3, 6, $doc_TOTAL_ORDER, - 2);
		$rep->TextCol(6, 7,	$DisplayTotal, -2);
		$rep->Font();	
		if ($email == 1)
		{
			if ($myrow['contact_email'] == '')
			{
				$myrow['contact_email'] = $branch['email'];
				$myrow['DebtorName'] = $branch['br_name'];
			}
			$rep->End($email, $doc_Invoice_no . " " . $myrow['reference'], $myrow);
		}	
	}
	if ($email == 0)
		$rep->End();
}

?>
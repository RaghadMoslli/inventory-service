package com.shop.inventory.grpc;

import com.shop.inventory.service.InventoryService;
import io.grpc.Status;
import io.grpc.stub.StreamObserver;
import net.devh.boot.grpc.server.service.GrpcService;

import java.util.List;

@GrpcService
public class InventoryGrpcService extends InventoryServiceGrpc.InventoryServiceImplBase {

    private final InventoryService inventoryService;

    public InventoryGrpcService(InventoryService inventoryService) {
        this.inventoryService = inventoryService;
    }

    @Override
    public void getStock(GetStockRequest request, StreamObserver<GetStockResponse> responseObserver) {
        try {
            List<InventoryService.StockItem> stocks = inventoryService.getStock(request.getProductIdsList());

            GetStockResponse.Builder response = GetStockResponse.newBuilder();
            for (InventoryService.StockItem stock : stocks) {
                response.addItems(StockItem.newBuilder()
                        .setProductId(stock.productId())
                        .setAvailable(stock.available())
                        .build());
            }

            responseObserver.onNext(response.build());
            responseObserver.onCompleted();
        } catch (Exception ex) {
            responseObserver.onError(Status.INTERNAL
                    .withDescription(ex.getMessage())
                    .withCause(ex)
                    .asRuntimeException());
        }
    }

    @Override
    public void reserve(ReserveRequest request, StreamObserver<ReserveResponse> responseObserver) {
        try {
            List<InventoryService.ItemRequest> items = request.getItemsList().stream()
                    .map(item -> new InventoryService.ItemRequest(item.getProductId(), item.getQuantity()))
                    .toList();

            InventoryService.ReservationResult result =
                    inventoryService.reserveStock(request.getRequestId(), items);

            ReserveResponse.Builder response = ReserveResponse.newBuilder()
                    .setSuccess(result.success());

            if (result.reservationId() != null) {
                response.setReservationId(result.reservationId());
            }

            for (InventoryService.ShortageItem shortage : result.shortages()) {
                response.addShortages(Shortage.newBuilder()
                        .setProductId(shortage.productId())
                        .setRequested(shortage.requested())
                        .setAvailable(shortage.available())
                        .build());
            }

            responseObserver.onNext(response.build());
            responseObserver.onCompleted();
        } catch (IllegalArgumentException ex) {
            responseObserver.onError(Status.INVALID_ARGUMENT
                    .withDescription(ex.getMessage())
                    .asRuntimeException());
        } catch (Exception ex) {
            responseObserver.onError(Status.INTERNAL
                    .withDescription(ex.getMessage())
                    .withCause(ex)
                    .asRuntimeException());
        }
    }

    @Override
    public void release(ReleaseRequest request, StreamObserver<ReleaseResponse> responseObserver) {
        try {
            boolean released = inventoryService.releaseStock(request.getReservationId());
            responseObserver.onNext(ReleaseResponse.newBuilder().setReleased(released).build());
            responseObserver.onCompleted();
        } catch (Exception ex) {
            responseObserver.onError(Status.INTERNAL
                    .withDescription(ex.getMessage())
                    .withCause(ex)
                    .asRuntimeException());
        }
    }

    @Override
    public void setStock(SetStockRequest request, StreamObserver<SetStockResponse> responseObserver) {
        try {
            boolean ok = inventoryService.setStock(request.getProductId(), request.getQuantity());
            responseObserver.onNext(SetStockResponse.newBuilder().setOk(ok).build());
            responseObserver.onCompleted();
        } catch (Exception ex) {
            responseObserver.onError(Status.INTERNAL
                    .withDescription(ex.getMessage())
                    .withCause(ex)
                    .asRuntimeException());
        }
    }
}
